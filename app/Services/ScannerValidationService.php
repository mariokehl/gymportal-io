<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use Carbon\Carbon;

class ScannerValidationService
{
    private int $qrCodeValidityMinutes = 30;

    /**
     * Generiert einen sicheren QR-Code für ein Mitglied
     */
    public function generateSecureQrCode(Member $member): array
    {
        $gym = $member->gym;

        if (!$gym->scanner_secret_key) {
            $gym->generateScannerSecretKey();
        }

        $timestamp = now()->toIso8601ZuluString();
        $message = "{$member->id}:{$timestamp}";

        $hash = hash_hmac('sha256', $message, $gym->scanner_secret_key);

        return [
            'member_id' => $member->id,
            'timestamp' => $timestamp,
            'hash' => $hash,
            'gym_id' => $gym->id
        ];
    }

    /**
     * Validiert einen QR-Code für Zugangskontrolle.
     *
     * Guest-Service QR-Codes (type=guest_service) werden hier explizit
     * abgelehnt, da sie ein fundamental anderes Format und einen anderen
     * HMAC-Prefix verwenden und nicht für den Zugang gedacht sind.
     */
    public function validateQrCode(array $qrData, string $gymId): array
    {
        try {
            // Guest-Service QR-Codes dürfen nicht als Zugangs-QR validiert werden
            if (($qrData['type'] ?? null) === 'guest_service') {
                return [
                    'valid' => false,
                    'message' => 'Guest-Service QR-Code ist kein Zugangs-QR'
                ];
            }

            $gym = Gym::findOrFail($gymId);

            // Zeitstempel prüfen
            $qrTime = Carbon::parse($qrData['timestamp']);
            $minutesOld = $qrTime->diffInMinutes(now());

            if ($minutesOld > $this->qrCodeValidityMinutes) {
                return [
                    'valid' => false,
                    'message' => 'QR-Code abgelaufen'
                ];
            }

            // Hash validieren (prüft beide Keys bei Rotation)
            if (!$gym->validateHash(
                $qrData['member_id'],
                $qrData['timestamp'],
                $qrData['hash']
            )) {
                return [
                    'valid' => false,
                    'message' => 'Ungültiger Hash'
                ];
            }

            // Mitgliedschaft prüfen
            $member = Member::find($qrData['member_id']);
            if (!$member || !$member->activeMembership()) {
                return [
                    'valid' => false,
                    'message' => 'Keine aktive Mitgliedschaft'
                ];
            }

            return [
                'valid' => true,
                'message' => 'Zugang gewährt',
                'member' => $member
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Validierungsfehler: ' . $e->getMessage()
            ];
        }
    }

    public function validateNfcCard(string $cardId): array
    {
        $accessConfig = MemberAccessConfig::where('nfc_uid', $cardId)->first();
        $member = $accessConfig?->member;

        return [
            'valid' => $member ? true : false,
            'member_id' => $member?->id,
            'scan_type' => 'nfc_card',
            'message' => 'Zugang gewährt'
        ];
    }
}
