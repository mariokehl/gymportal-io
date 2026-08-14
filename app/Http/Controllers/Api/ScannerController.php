<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\ScannerAccessLog;
use App\Services\DeviceAccessService;
use App\Services\ScannerValidationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ScannerController extends Controller
{
    public function __construct(
        private ScannerValidationService $validationService,
        private DeviceAccessService $deviceAccessService
    ) {}

    /**
     * Scanner reports itself as online.
     *
     * Route: GET /api/scanner/ping
     *
     * Cache-first heartbeat: writes a timestamp to the cache on every ping,
     * but updates the DB (last_seen_at) at most once per 60 minutes.
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var GymScanner $scanner */
        $scanner = $request->get('scanner');
        $cacheKey = "scanner_heartbeat:{$scanner->id}";

        // Refresh the cached timestamp on every ping (TTL 25 min → tolerates one missed 12-min ping)
        Cache::put($cacheKey, now()->toIso8601String(), now()->addMinutes(25));

        // Only touch the DB if the last DB write is more than 60 minutes ago
        $dbThrottleKey = "scanner_db_throttle:{$scanner->id}";
        if (! Cache::has($dbThrottleKey)) {
            $scanner->touch();
            Cache::put($dbThrottleKey, true, now()->addMinutes(60));
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Scanner checks whether the membership is valid.
     *
     * Route: GET /api/scanner/verify-membership
     *
     * The scanner client reads the decision from the status code, so these are
     * part of the contract: 200 carries the decision in the body, 400 marks a
     * malformed scan, 403 a deliberate denial, 404 an unknown member or card
     * (including one belonging to another gym), 500 an unexpected failure.
     */
    public function verifyMembership(Request $request): JsonResponse|Response
    {
        $validator = Validator::make($request->all(), [
            'scan_type' => 'required|string|in:qr_code,nfc_card,rolling_qr',
            'member_id' => 'required_if:scan_type,qr_code|required_if:scan_type,rolling_qr|string',
            'nfc_card_id' => 'required_if:scan_type,nfc_card|string',
        ]);

        if ($validator->fails()) {
            return new Response(status: 400);
        }

        $scanner = $request->get('scanner');
        $scanType = $request->input('scan_type');
        $checkInMethod = $scanType === 'rolling_qr' ? 'qr_code' : $scanType;
        $nfcCardId = $request->input('nfc_card_id');
        $member = null;

        try {
            // Resolve the member based on the scan type. Every lookup stays
            // inside the scanner's gym — a device must never open for a member
            // of another studio, so a foreign ID reads as unknown.
            if ($scanType === 'qr_code' || $scanType === 'rolling_qr') {
                $memberId = $request->input('member_id');
                $member = Member::where('gym_id', $scanner->gym_id)
                    ->whereKey($memberId)
                    ->first();
            } elseif ($scanType === 'nfc_card') {
                $accessConfig = MemberAccessConfig::where('nfc_uid', $nfcCardId)
                    ->whereHas('member', fn ($query) => $query->where('gym_id', $scanner->gym_id))
                    ->first();

                if ($accessConfig && ! $accessConfig->nfc_enabled) {
                    $this->logAccessFromVerify(
                        $scanner,
                        $accessConfig->member_id,
                        $scanType,
                        false,
                        'NFC-Zugang ist deaktiviert',
                        $nfcCardId
                    );

                    return new Response(status: 403);
                }

                $member = $accessConfig?->member;
            }

            // Member not found
            if (! $member) {
                $this->logAccessFromVerify(
                    $scanner,
                    $request->input('member_id', ''),
                    $scanType,
                    false,
                    $scanType === 'nfc_card' ? 'Unbekannte NFC-Karte' : 'Mitglied nicht gefunden',
                    $nfcCardId
                );

                return new Response(status: 404);
            }

            // Member is not active (e.g. locked, cancelled, pending)
            if (! $member->isActive()) {
                $this->logAccessFromVerify(
                    $scanner,
                    $member->id,
                    $scanType,
                    false,
                    'Mitglied ist nicht aktiv (Status: '.$member->status.')',
                    $nfcCardId
                );

                return new Response(status: 403);
            }

            // Already checked in within the last 30 seconds: grant access right
            // away. This only applies to access devices — a dispenser must still
            // check the booked service, otherwise a member without a drink
            // package would get through right after checking in.
            $recentCheckIn = $scanner->isAddonLinked()
                ? null
                : CheckIn::where('member_id', $member->id)
                    ->where('check_in_time', '>=', now()->subSeconds(30))
                    ->first();

            if ($recentCheckIn) {
                return response()->json([
                    'member_id' => $member->id,
                    'active' => $member->isActive(),
                    'membership_expires' => $recentCheckIn->check_in_time,
                    'access_allowed' => true,
                    'scan_type' => $scanType,
                    'message' => 'Zugang bereits gewährt',
                ]);
            }

            // Guest access: skip the membership check
            if ($member->hasGuestAccess()) {
                // Add-ons are only sold together with a membership, so there is
                // nothing for a guest to get at a dispenser.
                [$serviceAllowed, $serviceDenial] = $this->deviceAccessService->check(
                    $scanner,
                    $member,
                    null
                );

                if (! $serviceAllowed) {
                    $this->logAccessFromVerify(
                        $scanner,
                        $member->id,
                        $scanType,
                        false,
                        $serviceDenial,
                        $nfcCardId
                    );
                    $this->deviceAccessService->logUsage(
                        $scanner,
                        $member,
                        $scanType,
                        false,
                        $serviceDenial
                    );

                    return $this->deniedResponse($serviceDenial);
                }

                $this->logAccessFromVerify(
                    $scanner,
                    $member->id,
                    $scanType,
                    true,
                    null,
                    $nfcCardId
                );

                CheckIn::create([
                    'member_id' => $member->id,
                    'gym_id' => $member->gym_id,
                    'check_in_time' => now(),
                    'check_in_method' => $checkInMethod,
                ]);

                return response()->json([
                    'member_id' => $member->id,
                    'active' => $member->isActive(),
                    'membership_expires' => null,
                    'access_allowed' => true,
                    'scan_type' => $scanType,
                    'message' => 'Zugang gewährt (Gastzugang)',
                ]);
            }

            // Check for an active membership
            $activeMembership = $member->activeMembership();

            if (! $activeMembership) {
                $this->logAccessFromVerify(
                    $scanner,
                    $member->id,
                    $scanType,
                    false,
                    'Keine aktive Mitgliedschaft',
                    $nfcCardId
                );

                return new Response(status: 403);
            }

            // Check whether start_date lies in the past
            if ($activeMembership->start_date->isPast() || $activeMembership->start_date->isToday()) {
                // Membership is valid — now check whether the device's service
                // (e.g. drink package) has been booked as well.
                [$serviceAllowed, $serviceDenial] = $this->deviceAccessService->check(
                    $scanner,
                    $member,
                    $activeMembership
                );

                if (! $serviceAllowed) {
                    $this->logAccessFromVerify(
                        $scanner,
                        $member->id,
                        $scanType,
                        false,
                        $serviceDenial,
                        $nfcCardId
                    );
                    $this->deviceAccessService->logUsage(
                        $scanner,
                        $member,
                        $scanType,
                        false,
                        $serviceDenial
                    );

                    return $this->deniedResponse($serviceDenial);
                }

                $this->logAccessFromVerify(
                    $scanner,
                    $member->id,
                    $scanType,
                    true,
                    null,
                    $nfcCardId
                );
                $this->deviceAccessService->logUsage($scanner, $member, $scanType, true);

                // A dispenser scan is not a gym visit — only access devices
                // write a check-in.
                if (! $scanner->isAddonLinked()) {
                    CheckIn::create([
                        'member_id' => $member->id,
                        'gym_id' => $member->gym_id,
                        'check_in_time' => now(),
                        'check_in_method' => $checkInMethod,
                    ]);
                }

                return response()->json([
                    'member_id' => $member->id,
                    'active' => $member->isActive(),
                    'membership_expires' => $activeMembership->end_date,
                    'access_allowed' => true,
                    'scan_type' => $scanType,
                    'message' => 'Zugang gewährt',
                ]);
            } else {
                // Membership has not started yet
                $this->logAccessFromVerify(
                    $scanner,
                    $member->id,
                    $scanType,
                    false,
                    'Mitgliedschaft startet am '.$activeMembership->start_date->format('d.m.Y'),
                    $nfcCardId
                );

                return new Response(status: 403);
            }

        } catch (Exception $e) {
            Log::error('Membership verification error', [
                'error' => $e->getMessage(),
                'scan_type' => $scanType,
            ]);

            if ($scanner) {
                $this->logAccessFromVerify(
                    $scanner,
                    $member?->id ?? $request->input('member_id', ''),
                    $scanType,
                    false,
                    'Systemfehler',
                    $nfcCardId
                );
            }

            return new Response(status: 500);
        }
    }

    /**
     * Scanner validation endpoint.
     *
     * Route: POST /api/scanner/validate (currently not registered)
     *
     * Answers in the plain-text "code=NNNN" format the old scanner firmware
     * expected, always with status 200.
     *
     * @deprecated The scanner client validates the scan on the device itself
     *             (qr-scanner-server.py) and only calls verify-membership.
     *             No route points here anymore, see routes/api.php. Note that
     *             this path is broken as written: it hands a Gym model to
     *             handleQrCode(), whose service expects a gym ID string.
     */
    public function validateAccess(Request $request): Response
    {
        // The scanner has already been authenticated by the middleware
        $scanner = $request->input('scanner');
        $gym = $scanner->gym;

        // Parse the scanner payload
        $scanData = $request->input('vgdecoderesult', '');

        if (empty($scanData)) {
            $this->logAccess($scanner, null, 'empty_data', false);

            return response('code=9001', 200)
                ->header('Content-Type', 'text/plain');
        }

        try {
            // QR code or NFC?
            if ($this->isJsonQrCode($scanData)) {
                $result = $this->handleQrCode($scanData, $gym);
            } else {
                $result = $this->handleNfcCard($scanData);
            }

            // Access Log
            $this->logAccess(
                $scanner,
                $result['member_id'] ?? null,
                $result['scan_type'],
                $result['valid'],
                $result['message'] ?? null
            );

            // Determine the response code
            $responseCode = $this->determineResponseCode($result);

            return response("code={$responseCode}", 200)
                ->header('Content-Type', 'text/plain');

        } catch (Exception $e) {
            Log::error('Scanner validation error', [
                'scanner_id' => $scanner->id,
                'error' => $e->getMessage(),
            ]);

            $this->logAccess($scanner, null, 'error', false, $e->getMessage());

            return response('code=9999', 200)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * QR code validation.
     *
     * @param  Gym|string  $gym  Declared loosely on purpose: the only caller
     *                           passes a model, the service wants an ID.
     * @return array{valid: bool, scan_type: string, member_id: string|null, message?: string}
     *
     * @deprecated Only used by validateAccess().
     */
    private function handleQrCode(string $scanData, Gym|string $gym): array
    {
        $qrData = json_decode($scanData, true);

        if (! $qrData) {
            return [
                'valid' => false,
                'scan_type' => 'qr_code',
                'message' => 'Invalid JSON format',
            ];
        }

        // Validate the hash against the gym secret key
        $validationResult = $this->validationService->validateQrCode(
            $qrData,
            $gym
        );

        return array_merge($validationResult, [
            'scan_type' => 'qr_code',
            'member_id' => $qrData['member_id'] ?? null,
        ]);
    }

    /**
     * NFC card validation.
     *
     * @return array{valid: bool, scan_type: string, member_id: string|null, message?: string}
     *
     * @deprecated Only used by validateAccess().
     */
    private function handleNfcCard(string $cardId): array
    {
        return $this->validationService->validateNfcCard($cardId);
    }

    /**
     * Log an access attempt (for validateAccess).
     *
     * @deprecated Only used by validateAccess(); see logAccessFromVerify().
     */
    private function logAccess(
        GymScanner $scanner,
        string|int|null $memberId,
        string $scanType,
        bool $granted,
        ?string $message = null
    ): void {
        ScannerAccessLog::create([
            'gym_id' => $scanner->gym_id,
            'device_number' => $scanner->device_number,
            'member_id' => $memberId,
            'scan_type' => $scanType,
            'access_granted' => $granted,
            'denial_reason' => $granted ? null : $message,
            'metadata' => [
                'ip' => request()->ip(),
                'scanner_id' => $scanner->id,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Denial including a reason.
     *
     * Other denials answer with a bare 403. Here the reason matters to the
     * operator standing at the device ("service not booked" instead of "access
     * denied"), so it is carried in the body. Devices that ignore the body
     * still only see the status.
     */
    private function deniedResponse(?string $reason): JsonResponse
    {
        return response()->json([
            'access_allowed' => false,
            'message' => $reason ?? 'Zugang verweigert',
        ], 403);
    }

    /**
     * Log an access attempt (for verifyMembership).
     * Includes the NFC card ID in the metadata for unknown cards.
     *
     * @param  string|int  $memberId  Empty string when the scan could not be
     *                                resolved to a member at all.
     */
    private function logAccessFromVerify(
        ?GymScanner $scanner,
        string|int $memberId,
        string $scanType,
        bool $granted,
        ?string $message = null,
        ?string $nfcCardId = null
    ): void {
        if (! $scanner) {
            return;
        }

        $metadata = [
            'ip' => request()->ip(),
            'scanner_id' => $scanner->id,
            'timestamp' => now()->toIso8601String(),
        ];

        // Store the NFC card ID for unknown cards (important for the live log)
        if ($nfcCardId && ! $granted) {
            $metadata['nfc_card_id'] = $nfcCardId;
        }

        ScannerAccessLog::create([
            'gym_id' => $scanner->gym_id,
            'device_number' => $scanner->device_number,
            'member_id' => $memberId,
            'scan_type' => $scanType,
            'access_granted' => $granted,
            'denial_reason' => $granted ? null : $message,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Determine the response code.
     *
     * @param  array{valid: bool, message?: string}  $result
     *
     * @deprecated Only used by validateAccess().
     */
    private function determineResponseCode(array $result): string
    {
        if ($result['valid']) {
            return '0000'; // Access granted
        }

        $message = strtolower($result['message'] ?? '');

        if (str_contains($message, 'expired') || str_contains($message, 'abgelaufen')) {
            return '9004'; // Expired
        }

        if (str_contains($message, 'hash') || str_contains($message, 'gefälscht')) {
            return '9005'; // Invalid hash
        }

        if (str_contains($message, 'membership') || str_contains($message, 'mitglied')) {
            return '9003'; // No active membership
        }

        return '9002'; // Generic error
    }

    /**
     * Checks whether the payload is JSON (QR code).
     *
     * @deprecated Only used by validateAccess().
     */
    private function isJsonQrCode(mixed $data): bool
    {
        return is_string($data) &&
               (str_starts_with(trim($data), '{') || str_starts_with(trim($data), '['));
    }
}
