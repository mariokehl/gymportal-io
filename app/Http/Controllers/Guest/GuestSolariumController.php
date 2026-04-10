<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberAccessLog;
use App\Models\PendingSolariumRedemption;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GuestSolariumController extends Controller
{
    /**
     * Wie lange nach dem letzten guest_service CheckIn das Einlösen möglich ist.
     */
    private const CHECKIN_VALIDITY_HOURS = 4;

    /**
     * Guest löst Solarium-Guthaben ein. Erstellt eine pending redemption,
     * reserviert die Minuten sofort. Der Pi am Solarium-POI pollt und
     * schaltet das Shelly Relay.
     *
     * Route: POST /api/guests/solarium/redeem
     */
    public function redeem(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $validator = Validator::make($request->all(), [
            'minutes' => 'required|integer|min:1|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Ungültige Minuten-Angabe',
                'details' => $validator->errors(),
            ], 422);
        }

        $minutes = (int) $request->input('minutes');

        // Prüfen: Aktueller Check-In an einem Solarium-POI in den letzten 4h?
        $recentCheckIn = CheckIn::where('member_id', $member->id)
            ->where('check_in_method', 'guest_service')
            ->where('check_in_time', '>=', now()->subHours(self::CHECKIN_VALIDITY_HOURS))
            ->orderBy('check_in_time', 'desc')
            ->first();

        if (!$recentCheckIn) {
            return response()->json([
                'success' => false,
                'error' => 'Kein aktiver Check-In am Solarium-POI. Bitte scanne deinen QR-Code am Solarium.',
            ], 403);
        }

        $config = $member->accessConfig;
        if (!$config || !$config->solarium_enabled || $config->solarium_minutes < $minutes) {
            return response()->json([
                'success' => false,
                'error' => 'Nicht genügend Solarium-Guthaben',
                'available_minutes' => $config?->solarium_minutes ?? 0,
            ], 403);
        }

        // Bereits eine offene Redemption? Dann diese zurückgeben statt neue zu erstellen
        $existingPending = PendingSolariumRedemption::where('member_id', $member->id)
            ->where('status', PendingSolariumRedemption::STATUS_PENDING)
            ->where('created_at', '>=', now()->subSeconds(PendingSolariumRedemption::EXPIRY_SECONDS))
            ->first();

        if ($existingPending) {
            return response()->json([
                'success' => false,
                'error' => 'Es läuft bereits eine Einlösung. Bitte warte oder brich sie ab.',
                'redemption_id' => $existingPending->id,
            ], 409);
        }

        try {
            $redemption = DB::transaction(function () use ($member, $config, $minutes, $recentCheckIn) {
                // Guthaben sofort reservieren (abziehen)
                $config->decrement('solarium_minutes', $minutes);
                if ($config->fresh()->solarium_minutes <= 0) {
                    $config->update(['solarium_enabled' => false, 'solarium_minutes' => 0]);
                }

                $redemption = PendingSolariumRedemption::create([
                    'member_id' => $member->id,
                    'gym_id' => $recentCheckIn->gym_id,
                    'minutes' => $minutes,
                    'status' => PendingSolariumRedemption::STATUS_PENDING,
                ]);

                MemberAccessLog::create([
                    'member_id' => $member->id,
                    'action' => MemberAccessLog::ACTION_CREDIT_CONSUMED,
                    'success' => true,
                    'method' => MemberAccessLog::METHOD_QR,
                    'service' => MemberAccessLog::SERVICE_SOLARIUM,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'metadata' => [
                        'amount' => $minutes,
                        'reason' => 'redemption_reserved',
                        'redemption_id' => $redemption->id,
                    ],
                ]);

                return $redemption;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'redemption_id' => $redemption->id,
                    'status' => $redemption->status,
                    'minutes' => $redemption->minutes,
                    'expires_in_seconds' => PendingSolariumRedemption::EXPIRY_SECONDS,
                    'remaining_minutes' => $config->fresh()->solarium_minutes,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Solarium redeem error', [
                'member_id' => $member->id,
                'minutes' => $minutes,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Einlösung fehlgeschlagen',
            ], 500);
        }
    }

    /**
     * Status einer Redemption abfragen (Polling vom Frontend).
     * Route: GET /api/guests/solarium/redemption/{id}/status
     */
    public function status(Request $request, int $id): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $redemption = PendingSolariumRedemption::where('id', $id)
            ->where('member_id', $member->id)
            ->first();

        if (!$redemption) {
            return response()->json([
                'success' => false,
                'error' => 'Redemption nicht gefunden',
            ], 404);
        }

        // Auto-Expire on-read, falls älter als EXPIRY_SECONDS
        if ($redemption->isExpired()) {
            $this->expireRedemption($redemption);
            $redemption->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'redemption_id' => $redemption->id,
                'status' => $redemption->status,
                'minutes' => $redemption->minutes,
                'failure_reason' => $redemption->failure_reason,
                'created_at' => $redemption->created_at->toIso8601String(),
                'acknowledged_at' => $redemption->acknowledged_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * User bricht eine pending Redemption selbst ab und bekommt das Guthaben zurück.
     * Route: DELETE /api/guests/solarium/redemption/{id}
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $redemption = PendingSolariumRedemption::where('id', $id)
            ->where('member_id', $member->id)
            ->first();

        if (!$redemption) {
            return response()->json([
                'success' => false,
                'error' => 'Redemption nicht gefunden',
            ], 404);
        }

        if (!$redemption->isPending()) {
            return response()->json([
                'success' => false,
                'error' => 'Redemption ist nicht mehr offen',
                'current_status' => $redemption->status,
            ], 409);
        }

        try {
            DB::transaction(function () use ($redemption) {
                $redemption->update([
                    'status' => PendingSolariumRedemption::STATUS_CANCELLED,
                    'failure_reason' => 'cancelled_by_user',
                ]);

                $config = MemberAccessConfig::where('member_id', $redemption->member_id)->first();
                if ($config) {
                    $config->increment('solarium_minutes', $redemption->minutes);
                    if (!$config->solarium_enabled) {
                        $config->update(['solarium_enabled' => true]);
                    }

                    MemberAccessLog::create([
                        'member_id' => $redemption->member_id,
                        'action' => MemberAccessLog::ACTION_CREDIT_ADDED,
                        'success' => true,
                        'method' => MemberAccessLog::METHOD_QR,
                        'service' => MemberAccessLog::SERVICE_SOLARIUM,
                        'metadata' => [
                            'amount' => $redemption->minutes,
                            'reason' => 'cancelled_by_user',
                            'redemption_id' => $redemption->id,
                        ],
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Einlösung abgebrochen, Guthaben zurückgebucht',
            ]);
        } catch (Exception $e) {
            Log::error('Cancel redemption error', [
                'redemption_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Abbruch fehlgeschlagen',
            ], 500);
        }
    }

    private function expireRedemption(PendingSolariumRedemption $redemption): void
    {
        try {
            DB::transaction(function () use ($redemption) {
                $redemption->update([
                    'status' => PendingSolariumRedemption::STATUS_EXPIRED,
                    'failure_reason' => 'auto_expired_no_acknowledge',
                ]);

                $config = MemberAccessConfig::where('member_id', $redemption->member_id)->first();
                if ($config) {
                    $config->increment('solarium_minutes', $redemption->minutes);
                    if (!$config->solarium_enabled) {
                        $config->update(['solarium_enabled' => true]);
                    }
                }
            });
        } catch (Exception $e) {
            Log::error('Expire redemption failed', [
                'redemption_id' => $redemption->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
