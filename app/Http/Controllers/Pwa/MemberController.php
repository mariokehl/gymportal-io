<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Mail\CancellationConfirmationMail;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
                    'is_verified' => true
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
                'is_verified' => false
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
        ]);

        $member = request()->user();
        $member->update($request->only([
            'first_name', 'last_name', 'phone',
            'address', 'city', 'postal_code'
        ]));

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
        /** @var Membership $membership */
        $membership = $member->activeMembership();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Keine aktive Mitgliedschaft gefunden'
            ], 404);
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

    public function generateQrCode(): JsonResponse
    {
        /** @var Member $member */
        $member = request()->user();

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
}
