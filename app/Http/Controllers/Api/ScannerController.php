<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $scanType = $request->input('scan_type');
        $member = null;

        try {
            // Mitglied basierend auf Scan-Typ ermitteln
            if ($scanType === 'qr_code') {
                $memberId = $request->input('member_id');
                $member = Member::find($memberId);
            } elseif ($scanType === 'nfc_card') {
                $nfcCardId = $request->input('nfc_card_id');
                $accessConfig = MemberAccessConfig::where('nfc_uid', $nfcCardId)->first();
                $member = $accessConfig?->member;
            }

            // Mitglied nicht gefunden
            if (!$member) {
                Log::info('Member not found', [
                    'scan_type' => $scanType,
                    'member_id' => $request->input('member_id'),
                    'nfc_card_id' => $request->input('nfc_card_id')
                ]);
                return response(status: 404);
            }

            // Aktive Mitgliedschaft prüfen
            $activeMembership = $member->activeMembership();

            if (!$activeMembership) {
                Log::info('No active membership', [
                    'member_id' => $member->id,
                    'scan_type' => $scanType
                ]);
                return response(status: 403);
            }

            // Prüfen ob start_date in der Vergangenheit liegt
            if ($activeMembership->start_date->isPast() || $activeMembership->start_date->isToday()) {
                // Gültige Mitgliedschaft
                Log::info('Valid membership access', [
                    'member_id' => $member->id,
                    'membership_id' => $activeMembership->id,
                    'scan_type' => $scanType
                ]);

                // TODO: Check-In protokollieren
                //...

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
                Log::info('Membership not yet started', [
                    'member_id' => $member->id,
                    'membership_id' => $activeMembership->id,
                    'start_date' => $activeMembership->start_date->toDateString(),
                    'scan_type' => $scanType
                ]);

                return response(status: 403);
            }

        } catch (Exception $e) {
            Log::error('Membership verification error', [
                'error' => $e->getMessage(),
                'scan_type' => $scanType
            ]);

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
     * Zugangsversuch protokollieren
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
