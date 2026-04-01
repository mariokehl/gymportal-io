<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\ScannerAccessLog;
use App\Services\ScannerValidationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ScannerController extends Controller
{
    public function __construct(
        private ScannerValidationService $validationService
    ) {}

    /**
     * Scanner meldet sich als online
     * Route: GET /api/scanner/ping
     *
     * Cache-First-Heartbeat: Schreibt einen Zeitstempel in den Cache bei jedem Ping,
     * aktualisiert die DB (last_seen_at) aber maximal 1x pro 60 Minuten.
     */
    public function ping(Request $request)
    {
        $scanner = $request->get('scanner');
        $cacheKey = "scanner_heartbeat:{$scanner->id}";

        // Cache-Zeitstempel bei jedem Ping aktualisieren (TTL 25 Min → toleriert einen ausgefallenen 12-Min-Ping)
        Cache::put($cacheKey, now()->toIso8601String(), now()->addMinutes(25));

        // DB nur aktualisieren, wenn letzter DB-Write > 60 Minuten her
        $dbThrottleKey = "scanner_db_throttle:{$scanner->id}";
        if (!Cache::has($dbThrottleKey)) {
            $scanner->touch();
            Cache::put($dbThrottleKey, true, now()->addMinutes(60));
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Scanner prüft auf Gültigkeit der Mitgliedschaft
     * Route: GET /api/scanner/verify-membership
     */
    public function verifyMembership(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scan_type' => 'required|string|in:qr_code,nfc_card,rolling_qr,guest_service',
            'member_id' => 'required_if:scan_type,qr_code|required_if:scan_type,rolling_qr|required_if:scan_type,guest_service|string',
            'nfc_card_id' => 'required_if:scan_type,nfc_card|string'
        ]);

        if ($validator->fails()) {
            return response(status: 400);
        }

        $scanner = $request->get('scanner');
        $scanType = $request->input('scan_type');
        $checkInMethod = match ($scanType) {
            'rolling_qr' => 'qr_code',
            'guest_service' => 'guest_service',
            default => $scanType,
        };
        $nfcCardId = $request->input('nfc_card_id');
        $member = null;

        try {
            // Mitglied basierend auf Scan-Typ ermitteln
            if ($scanType === 'qr_code' || $scanType === 'rolling_qr') {
                $memberId = $request->input('member_id');
                $member = Member::find($memberId);
            } elseif ($scanType === 'nfc_card') {
                $accessConfig = MemberAccessConfig::where('nfc_uid', $nfcCardId)->first();

                if ($accessConfig && !$accessConfig->nfc_enabled) {
                    $this->logAccessFromVerify(
                        $scanner,
                        $accessConfig->member_id,
                        $scanType,
                        false,
                        'NFC-Zugang ist deaktiviert',
                        $nfcCardId
                    );
                    return response(status: 403);
                }

                $member = $accessConfig?->member;
            }

            // Mitglied nicht gefunden
            if (!$member) {
                $this->logAccessFromVerify(
                    $scanner,
                    $request->input('member_id', ''),
                    $scanType,
                    false,
                    $scanType === 'nfc_card' ? 'Unbekannte NFC-Karte' : 'Mitglied nicht gefunden',
                    $nfcCardId
                );
                return response(status: 404);
            }

            // Mitglied ist nicht aktiv und kein Externer (z.B. gesperrt, gekündigt, ausstehend)
            if (!$member->isActive() && !$member->isExtern()) {
                $this->logAccessFromVerify(
                    $scanner,
                    $member->id,
                    $scanType,
                    false,
                    'Mitglied ist nicht aktiv (Status: ' . $member->status . ')',
                    $nfcCardId
                );
                return response(status: 403);
            }

            // Bereits in den letzten 30 Sekunden eingecheckt: sofort Zugang gewähren
            $recentCheckIn = CheckIn::where('member_id', $member->id)
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

            // Guest-Service QR-Code: Fundamental anderes Format als der normale Zugangs-QR.
            // Tageskarte/10er-Karte gewähren Zugang, reines Solarium-Guthaben nicht.
            if ($scanType === 'guest_service') {
                $config = $member->accessConfig;

                if (!$config) {
                    $this->logAccessFromVerify(
                        $scanner, $member->id, $scanType, false,
                        'Keine Zugangs-Konfiguration vorhanden', $nfcCardId
                    );
                    return response(status: 403);
                }

                // Verfügbare Service-Guthaben ermitteln
                $services = [];
                if ($config->solarium_enabled && $config->solarium_minutes > 0) {
                    $services['solarium_minutes'] = $config->solarium_minutes;
                }
                if ($config->visit_card_enabled && $config->visit_card_entries > 0) {
                    $services['visit_card_entries'] = $config->visit_card_entries;
                }
                if ($config->day_pass_enabled && $config->isDayPassValid()) {
                    $services['day_pass_valid_until'] = $config->day_pass_valid_until->toIso8601String();
                }

                if (empty($services)) {
                    $this->logAccessFromVerify(
                        $scanner, $member->id, $scanType, false,
                        'Kein Service-Guthaben vorhanden', $nfcCardId
                    );
                    return response()->json([
                        'member_id' => $member->id,
                        'access_allowed' => false,
                        'scan_type' => 'guest_service',
                        'message' => 'Kein Guthaben vorhanden',
                    ], 403);
                }

                // Tageskarte oder 10er-Karte berechtigen zum Zugang (Tür öffnen),
                // reines Solarium-Guthaben hingegen NICHT.
                $hasAccessEntitlement = ($config->day_pass_enabled && $config->isDayPassValid())
                    || ($config->visit_card_enabled && $config->visit_card_entries > 0);

                if ($hasAccessEntitlement) {
                    CheckIn::create([
                        'member_id' => $member->id,
                        'gym_id' => $member->gym_id,
                        'check_in_time' => now(),
                        'check_in_method' => 'guest_service',
                    ]);
                }

                $this->logAccessFromVerify(
                    $scanner, $member->id, $scanType, true, null, $nfcCardId
                );

                return response()->json([
                    'member_id' => $member->id,
                    'access_allowed' => $hasAccessEntitlement,
                    'service_allowed' => true,
                    'scan_type' => 'guest_service',
                    'services' => $services,
                    'member' => $member->only(['first_name', 'last_name', 'member_number']),
                    'message' => $hasAccessEntitlement
                        ? 'Zugang gewährt (Gäste-Service)'
                        : 'Service-Guthaben verfügbar (kein Zugang)',
                ]);
            }

            // Gastzugang: Überspringe Mitgliedschaftsprüfung
            if ($member->hasGuestAccess()) {
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

            // Aktive Mitgliedschaft prüfen
            $activeMembership = $member->activeMembership();

            if (!$activeMembership) {
                $this->logAccessFromVerify(
                    $scanner,
                    $member->id,
                    $scanType,
                    false,
                    'Keine aktive Mitgliedschaft',
                    $nfcCardId
                );
                return response(status: 403);
            }

            // Prüfen ob start_date in der Vergangenheit liegt
            if ($activeMembership->start_date->isPast() || $activeMembership->start_date->isToday()) {
                // Gültige Mitgliedschaft - Zugang gewährt
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
                    'membership_expires' => $activeMembership->end_date,
                    'access_allowed' => true,
                    'scan_type' => $scanType,
                    'message' => 'Zugang gewährt',
                ]);
            } else {
                // Mitgliedschaft noch nicht gestartet
                $this->logAccessFromVerify(
                    $scanner,
                    $member->id,
                    $scanType,
                    false,
                    'Mitgliedschaft startet am ' . $activeMembership->start_date->format('d.m.Y'),
                    $nfcCardId
                );

                return response(status: 403);
            }

        } catch (Exception $e) {
            Log::error('Membership verification error', [
                'error' => $e->getMessage(),
                'scan_type' => $scanType
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

            return response(status: 500);
        }
    }

    /**
     * Scanner-Validierung Endpoint
     * Route: POST /api/scanner/validate
     *
     * @deprecated Wird aktuell direkt auf dem Scanner (qr-scanner-server.py) validiert.
     */
    public function validateAccess(Request $request)
    {
        // Scanner wurde bereits durch Middleware authentifiziert
        $scanner = $request->input('scanner');
        $gym = $scanner->gym;

        // Scanner-Daten parsen
        $scanData = $request->input('vgdecoderesult', '');

        if (empty($scanData)) {
            $this->logAccess($scanner, null, 'empty_data', false);
            return response('code=9001', 200)
                ->header('Content-Type', 'text/plain');
        }

        try {
            // QR-Code oder NFC?
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

            // Response Code bestimmen
            $responseCode = $this->determineResponseCode($result);

            return response("code={$responseCode}", 200)
                ->header('Content-Type', 'text/plain');

        } catch (Exception $e) {
            Log::error('Scanner validation error', [
                'scanner_id' => $scanner->id,
                'error' => $e->getMessage()
            ]);

            $this->logAccess($scanner, null, 'error', false, $e->getMessage());

            return response('code=9999', 200)
                ->header('Content-Type', 'text/plain');
        }
    }

    /**
     * QR-Code Validierung
     */
    private function handleQrCode($scanData, $gym)
    {
        $qrData = json_decode($scanData, true);

        if (!$qrData) {
            return [
                'valid' => false,
                'scan_type' => 'qr_code',
                'message' => 'Invalid JSON format'
            ];
        }

        // Guest-Service QR-Codes dürfen NICHT als normaler Zugang validiert werden
        if (($qrData['type'] ?? null) === 'guest_service') {
            return [
                'valid' => false,
                'scan_type' => 'guest_service',
                'member_id' => $qrData['mid'] ?? null,
                'message' => 'Guest-Service QR-Code ist kein Zugangs-QR'
            ];
        }

        // Hash mit Gym Secret Key validieren
        $validationResult = $this->validationService->validateQrCode(
            $qrData,
            $gym
        );

        return array_merge($validationResult, [
            'scan_type' => 'qr_code',
            'member_id' => $qrData['member_id'] ?? null
        ]);
    }

    /**
     * NFC-Karten Validierung
     */
    private function handleNfcCard($cardId)
    {
        return $this->validationService->validateNfcCard($cardId);
    }

    /**
     * Zugangsversuch protokollieren (für validateAccess)
     */
    private function logAccess($scanner, $memberId, $scanType, $granted, $message = null)
    {
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
                'timestamp' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * Zugangsversuch protokollieren (für verifyMembership)
     * Inkludiert NFC-Karten-ID in metadata für unbekannte Karten
     */
    private function logAccessFromVerify($scanner, $memberId, $scanType, $granted, $message = null, $nfcCardId = null)
    {
        if (!$scanner) {
            return;
        }

        $metadata = [
            'ip' => request()->ip(),
            'scanner_id' => $scanner->id,
            'timestamp' => now()->toIso8601String()
        ];

        // NFC-Karten-ID für unbekannte Karten speichern (wichtig für Live-Protokoll)
        if ($nfcCardId && !$granted) {
            $metadata['nfc_card_id'] = $nfcCardId;
        }

        ScannerAccessLog::create([
            'gym_id' => $scanner->gym_id,
            'device_number' => $scanner->device_number,
            'member_id' => $memberId,
            'scan_type' => $scanType,
            'access_granted' => $granted,
            'denial_reason' => $granted ? null : $message,
            'metadata' => $metadata
        ]);
    }

    /**
     * Response Code bestimmen
     */
    private function determineResponseCode($result): string
    {
        if ($result['valid']) {
            return '0000'; // Zugang gewährt
        }

        $message = strtolower($result['message'] ?? '');

        if (str_contains($message, 'expired') || str_contains($message, 'abgelaufen')) {
            return '9004'; // Abgelaufen
        }

        if (str_contains($message, 'hash') || str_contains($message, 'gefälscht')) {
            return '9005'; // Ungültiger Hash
        }

        if (str_contains($message, 'membership') || str_contains($message, 'mitglied')) {
            return '9003'; // Keine aktive Mitgliedschaft
        }

        return '9002'; // Allgemeiner Fehler
    }

    /**
     * Prüft ob es sich um JSON (QR-Code) handelt
     */
    private function isJsonQrCode($data): bool
    {
        return is_string($data) &&
               (str_starts_with(trim($data), '{') || str_starts_with(trim($data), '['));
    }
}
