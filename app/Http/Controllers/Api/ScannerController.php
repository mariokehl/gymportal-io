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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ScannerController extends Controller
{
    public function __construct(
        private ScannerValidationService $validationService
    ) {}

    /**
     * Scanner prüft auf Gültigkeit der Mitgliedschaft
     * Route: GET /api/scanner/verify-membership
     */
    public function verifyMembership(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scan_type' => 'required|string|in:qr_code,nfc_card',
            'member_id' => 'required_if:scan_type,qr_code|string',
            'nfc_card_id' => 'required_if:scan_type,nfc_card|string'
        ]);

        if ($validator->fails()) {
            return response(status: 400);
        }

        $scanner = $request->get('scanner');
        $scanType = $request->input('scan_type');
        $nfcCardId = $request->input('nfc_card_id');
        $member = null;

        try {
            // Mitglied basierend auf Scan-Typ ermitteln
            if ($scanType === 'qr_code') {
                $memberId = $request->input('member_id');
                $member = Member::find($memberId);
            } elseif ($scanType === 'nfc_card') {
                $accessConfig = MemberAccessConfig::where('nfc_uid', $nfcCardId)->first();
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
                    'check_in_method' => $scanType,
                ]);

                return response()->json([
                    'member_id' => $member->id,
                    'active' => true,
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
