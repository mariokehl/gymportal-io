<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\WithdrawContractRequest;
use App\Mail\CancellationConfirmationMail;
use App\Mail\WithdrawalConfirmationMail;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\ContractWithdrawnNotification;
use App\Services\PaymentService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberController extends Controller
{
    public function profile(): JsonResponse
    {
        /** @var Member $member */
        $member = request()->user();
        $token = $member->currentAccessToken();

        // Check if token is anonymous or full
        $isVerified = $token && $token->can('full');

        if ($isVerified) {
            // Full session - return all data
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $member->id,
                    'member_number' => $member->member_number,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'address' => $member->address,
                    'postal_code' => $member->postal_code,
                    'city' => $member->city,
                    'birth_date' => $member->birth_date?->format('Y-m-d'),
                    'status' => $member->status,
                    'avatar_url' => null,
                    'joined_date' => $member->joined_date?->format('Y-m-d'),
                    'gym' => $member->gym ? [
                        'id' => $member->gym->id,
                        'name' => $member->gym->name,
                        'slug' => $member->gym->slug
                    ] : null,
                    'is_verified' => true,
                    'qr_code_enabled' => $member->accessConfig?->qr_code_enabled ?? false
                ],
            ]);
        }

        // Anonymous session - return masked data
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $member->id,
                'member_number' => $member->member_number,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'phone_masked' => $member->masked_phone,
                'address_masked' => $member->masked_address,
                'postal_code_masked' => $member->masked_postal_code,
                'city_masked' => $member->masked_city,
                'birth_date_masked' => $member->masked_birth_date,
                'status' => $member->status,
                'avatar_url' => null,
                'joined_date' => $member->joined_date?->format('Y-m-d'),
                'gym' => $member->gym ? [
                    'id' => $member->gym->id,
                    'name' => $member->gym->name,
                    'slug' => $member->gym->slug
                ] : null,
                'is_verified' => false,
                'qr_code_enabled' => $member->accessConfig?->qr_code_enabled ?? false
            ],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $member = request()->user();
        $member->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $member,
            'message' => 'Profil erfolgreich aktualisiert'
        ]);
    }

    public function contract(): JsonResponse
    {
        $member = request()->user();
        $contract = $member->activeMembership();

        return response()->json([
            'success' => true,
            'data' => $contract
        ]);
    }

    /**
     * Gibt eine Übersicht aller Mitgliedschaften zurück
     * (aktuelle, gratis und bezahlte Mitgliedschaften)
     */
    public function memberships(): JsonResponse
    {
        /** @var Member $member */
        $member = request()->user();
        $overview = $member->getMembershipOverview();

        return response()->json([
            'success' => true,
            'data' => [
                'current' => $overview['current'] ? $this->formatMembership($overview['current']) : null,
                'free' => $overview['free']->map(fn($m) => $this->formatMembership($m)),
                'paid' => $overview['paid']->map(fn($m) => $this->formatMembership($m)),
            ]
        ]);
    }

    /**
     * Formatiert eine Mitgliedschaft für die API-Antwort
     */
    private function formatMembership(Membership $membership): array
    {
        return [
            'id' => (int) $membership->id,
            'status' => (string) $membership->status,
            'status_text' => (string) $membership->status_text,
            'start_date' => $membership->start_date?->format('Y-m-d'),
            'end_date' => $membership->end_date?->format('Y-m-d'),
            'cancellation_date' => $membership->cancellation_date?->format('Y-m-d'),
            'is_free_trial' => (bool) $membership->is_free_trial,
            'plan' => $membership->is_free_trial ? null : $membership->membershipPlan,
            // Widerrufs-Informationen (gemäß § 356a BGB)
            'withdrawal_eligible' => (bool) $membership->withdrawal_eligible,
            'withdrawal_deadline' => $membership->withdrawal_deadline,
            'contract_start_date' => $membership->contract_start_date,
            'withdrawn_at' => $membership->withdrawn_at?->toIso8601String(),
        ];
    }

    public function updateContract(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|in:sepa,credit_card,bank_transfer',
            'iban' => 'required_if:payment_method,sepa|nullable|string',
            'account_holder' => 'required_if:payment_method,sepa|nullable|string',
        ]);

        $member = request()->user();
        $contract = $member->contract;

        if ($contract) {
            $contract->update($request->only([
                'payment_method', 'iban', 'account_holder'
            ]));
        }

        return response()->json([
            'success' => true,
            'data' => $contract,
            'message' => 'Zahlungsdaten erfolgreich aktualisiert'
        ]);
    }

    public function cancelContract(): JsonResponse
    {
        /** @var Member $member */
        $member = request()->user();
        /** @var Membership|null $activeMembership */
        $activeMembership = $member->activeMembership();

        if (!$activeMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Keine aktive Mitgliedschaft gefunden'
            ], 404);
        }

        // Gekündigt wird immer die bezahlte Mitgliedschaft
        // Bei Free-Trial: verlinkte bezahlte Mitgliedschaft ermitteln
        // Bei bezahlter Mitgliedschaft: direkt diese kündigen
        /** @var Membership|null $membership */
        $membership = $activeMembership->is_free_trial
            ? $activeMembership->linkedPaidMembership
            : $activeMembership;

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Keine kündigbare Mitgliedschaft gefunden'
            ], 422);
        }

        $membership->update([
            'cancellation_date' => $membership->default_cancellation_date,
            'cancellation_reason' => 'Sonstiges (Ordentliche Kündigung über PWA)',
            'notes' => 'Gekündigt am ' . now()->format('d.m.Y H:i')
        ]);

        // Send cancellation confirmation email
        try {
            Mail::to($member->email)->send(
                new CancellationConfirmationMail(
                    $member,
                    $membership->fresh(),
                    $member->gym
                )
            );
        } catch (Exception $e) {
            Log::error('Failed to send cancellation confirmation email', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Vertrag erfolgreich gekündigt',
            'data' => $membership->fresh()
        ]);
    }

    /**
     * Vertrag widerrufen gemäß § 356a BGB
     *
     * Zweistufiges Verfahren:
     * Stufe 1: Frontend zeigt Info-Dialog mit "Weiter"-Button
     * Stufe 2: Frontend zeigt Bestätigungs-Dialog, dieser Endpoint wird aufgerufen
     *
     * Gemäß § 356a BGB:
     * - Widerrufsfrist: 14 Tage ab Vertragsabschluss
     * - Eingangsbestätigung auf dauerhaftem Datenträger (E-Mail)
     * - Widerrufsgrund darf NICHT abgefragt werden
     */
    public function withdrawContract(WithdrawContractRequest $request, PaymentService $paymentService): JsonResponse
    {
        /** @var Member $member */
        $member = request()->user();

        $membership = Membership::where('id', $request->membership_id)
            ->where('member_id', $member->id)
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Mitgliedschaft nicht gefunden.',
            ], 404);
        }

        // Prüfen ob Widerruf möglich ist
        $withdrawalCheck = $this->checkWithdrawalEligibility($membership);

        if (!$withdrawalCheck['eligible']) {
            return response()->json([
                'success' => false,
                'message' => $withdrawalCheck['reason'],
            ], 422);
        }

        // E-Mail aus Request oder Profil für Bestätigung
        $confirmationEmail = $request->confirmation_email ?: $member->email;

        try {
            DB::beginTransaction();

            // Erstattungsbetrag berechnen
            $refundAmount = $this->calculateRefundAmount($membership);

            // Widerruf durchführen
            $membership->update([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
                'withdrawal_confirmation_sent_to' => $confirmationEmail,
                'withdrawal_refund_amount' => $refundAmount,
            ]);

            // Erstattung initiieren (falls Zahlungen vorhanden)
            if ($refundAmount > 0) {
                $paymentService->initiateRefund($membership, $refundAmount);
            }

            // Eingangsbestätigung senden (gemäß § 356a BGB auf dauerhaftem Datenträger)
            // WICHTIG: Die Bestätigung darf nur den Eingang bestätigen,
            // NICHT dass der Widerruf "wirksam" ist
            Mail::to($confirmationEmail)->send(new WithdrawalConfirmationMail(
                $member,
                $membership->fresh(),
                $member->gym,
                [
                    'withdrawal_date' => now()->format('d.m.Y'),
                    'withdrawal_time' => now()->format('H:i'),
                    'refund_amount' => $refundAmount,
                ]
            ));

            DB::commit();

            // Notification an Gym-Mitarbeiter senden (außerhalb der Transaktion)
            $this->notifyGymUsersAboutWithdrawal($member, $membership->fresh(), $refundAmount);

            Log::info('Contract withdrawn successfully', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'refund_amount' => $refundAmount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Dein Widerruf wurde erfolgreich registriert.',
                'data' => [
                    'withdrawal_date' => now()->toIso8601String(),
                    'confirmation_sent_to' => $confirmationEmail,
                    'refund_amount' => $refundAmount,
                    'refund_expected_date' => $refundAmount > 0
                        ? now()->addDays(14)->toIso8601String()
                        : null,
                ],
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Contract withdrawal failed', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Der Widerruf konnte nicht verarbeitet werden. Bitte versuche es erneut.',
            ], 500);
        }
    }

    /**
     * Prüft ob ein Widerruf möglich ist (§ 356a BGB)
     */
    private function checkWithdrawalEligibility(Membership $membership): array
    {
        // Nur bezahlte Mitgliedschaften können widerrufen werden
        if ($membership->is_free_trial) {
            return [
                'eligible' => false,
                'reason' => 'Kostenlose Mitgliedschaften können nicht widerrufen werden.',
            ];
        }

        // Bereits widerrufen?
        if ($membership->withdrawn_at) {
            return [
                'eligible' => false,
                'reason' => 'Diese Mitgliedschaft wurde bereits widerrufen.',
            ];
        }

        // Bereits gekündigt?
        if ($membership->status === 'cancelled') {
            return [
                'eligible' => false,
                'reason' => 'Gekündigte Verträge können nicht widerrufen werden.',
            ];
        }

        // Nur aktive oder pending Mitgliedschaften
        if (!in_array($membership->status, ['active', 'pending'])) {
            return [
                'eligible' => false,
                'reason' => 'Diese Mitgliedschaft kann nicht widerrufen werden.',
            ];
        }

        // Widerrufsfrist prüfen (14 Tage)
        $contractStartDate = $membership->contract_start_date;
        if (!$contractStartDate) {
            return [
                'eligible' => false,
                'reason' => 'Vertragsstartdatum konnte nicht ermittelt werden.',
            ];
        }

        $startDate = Carbon::parse($contractStartDate);
        $withdrawalDeadline = $startDate->copy()->addDays(14)->endOfDay();

        if (now()->isAfter($withdrawalDeadline)) {
            return [
                'eligible' => false,
                'reason' => 'Die 14-tägige Widerrufsfrist ist bereits abgelaufen.',
            ];
        }

        return [
            'eligible' => true,
            'reason' => null,
        ];
    }

    /**
     * Berechnet den Erstattungsbetrag für einen Widerruf
     */
    private function calculateRefundAmount(Membership $membership): float
    {
        // Alle abgeschlossenen Zahlungen für diese Mitgliedschaft abrufen
        $totalPaid = $membership->payments()
            ->whereIn('status', ['paid', 'completed'])
            ->sum('amount');

        return (float) $totalPaid;
    }

    public function generateQrCode(): JsonResponse
    {
        /** @var Member $member */
        $member = request()->user();

        // Prüfen, ob QR-Code-Generierung für dieses Mitglied erlaubt ist
        if (!$member->accessConfig || !$member->accessConfig->qr_code_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'QR-Code-Generierung ist für dieses Mitglied nicht aktiviert.'
            ], 403);
        }

        /** @var Gym $gym */
        $gym = $member->gym;

        // Zeitstempel im ISO 8601 Format mit Z-Suffix
        $timestamp = Carbon::now()->format('Y-m-d\TH:i:s.uP');

        // QR-Code-Daten
        $qrData = [
            'member_id' => (string) $member->id,
            'timestamp' => $timestamp
        ];

        // Hash generieren (über member_id und timestamp)
        $message = $member->id . ':' . $timestamp;
        $hashValue = hash_hmac(
            'sha256',
            $message,
            $gym->getCurrentScannerKey()
        );
        $qrData['hash'] = $hashValue;

        return response()->json([
            'success' => true,
            'data' => [
                'qr_code' => json_encode($qrData),
                'member' => $member->only(['first_name', 'last_name', 'member_number'])
            ]
        ]);
    }

    /**
     * Alle Gyms des eingeloggten Members abrufen.
     *
     * Gibt alle Gyms zurück, zu denen der Member Zugang hat.
     * Bei Multi-Gym-Mitgliedschaften können das mehrere sein.
     *
     * @return JsonResponse
     */
    public function gyms(): JsonResponse
    {
        /** @var Member $member */
        $member = request()->user();

        /** @var Gym $gym */
        $gym = $member->gym;

        // Gym(s) des Members laden mit relevanten Daten
        $gyms = Gym::where('owner_id', $gym->owner_id)
            ->get()
            ->map(function ($gym) {
                return [
                    'id' => $gym->id,
                    'slug' => $gym->slug,
                    'name' => $gym->name,
                    'address' => $gym->address,
                    'city' => $gym->city,
                    'postal_code' => $gym->postal_code,
                    'phone' => $gym->phone,
                    'email' => $gym->email,
                    'latitude' => (float) $gym->latitude,
                    'longitude' => (float) $gym->longitude,
                    'opening_hours' => $gym->opening_hours,
                    //'logo_url' => $gym->getFirstMediaUrl('logo'),
                    //'cover_image_url' => $gym->getFirstMediaUrl('cover'),
                    //'is_open' => $this->isGymOpen($gym),
                    //'current_occupancy' => $this->getCurrentOccupancy($gym),
                ];
            });

        return response()->json([
            'data' => [
                'gyms' => $gyms,
                'current_gym_id' => $member->gym_id,
            ],
        ]);
    }

    /**
     * Prüfen ob Gym aktuell geöffnet ist
     */
    private function isGymOpen($gym): bool
    {
        $now = now();
        $dayName = strtolower($now->englishDayOfWeek);

        $todayHours = $gym->openingHours
            ->where('day', $dayName)
            ->where('is_closed', false)
            ->first();

        if (!$todayHours) {
            return false;
        }

        $openTime = $now->copy()->setTimeFromTimeString($todayHours->open_time);
        $closeTime = $now->copy()->setTimeFromTimeString($todayHours->close_time);

        // Falls Schließzeit nach Mitternacht
        if ($closeTime->lt($openTime)) {
            $closeTime->addDay();
        }

        return $now->between($openTime, $closeTime);
    }

    /**
     * Aktuelle Auslastung berechnen (optional)
     */
    private function getCurrentOccupancy($gym): ?int
    {
        // Aktive Check-ins zählen
        $activeCheckIns = $gym->checkIns()
            ->whereNull('checked_out_at')
            ->where('checked_in_at', '>=', now()->subHours(12))
            ->count();

        // Kapazität prüfen
        if (!$gym->max_capacity) {
            return null;
        }

        return (int) round(($activeCheckIns / $gym->max_capacity) * 100);
    }

    /**
     * Benachrichtigt alle Gym-Mitarbeiter über einen Vertragswiderruf
     */
    private function notifyGymUsersAboutWithdrawal(Member $member, Membership $membership, float $refundAmount): void
    {
        try {
            $gym = $member->gym;

            // Alle User des Gyms abrufen (nicht gelöscht, nicht blockiert)
            $gymUsers = User::where('current_gym_id', $gym->id)
                ->where('is_blocked', false)
                ->whereNull('deleted_at')
                ->get();

            // Owner hinzufügen, falls nicht bereits in der Liste
            if ($gym->owner_id && !$gymUsers->contains('id', $gym->owner_id)) {
                $owner = User::where('id', $gym->owner_id)
                    ->where('is_blocked', false)
                    ->whereNull('deleted_at')
                    ->first();

                if ($owner) {
                    $gymUsers->push($owner);
                }
            }

            // Notification an alle User senden
            foreach ($gymUsers as $user) {
                $user->notify(new ContractWithdrawnNotification(
                    $member,
                    $membership,
                    $gym,
                    $refundAmount
                ));
            }

            Log::info('Contract withdrawal notification sent', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'gym_id' => $gym->id,
                'notified_users' => $gymUsers->count(),
            ]);
        } catch (Exception $e) {
            // Notification-Fehler sollten den Widerruf nicht beeinflussen
            Log::error('Failed to send contract withdrawal notification', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
