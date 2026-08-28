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

        if (! $gym->scanner_secret_key) {
            $gym->generateScannerSecretKey();
        }

        $timestamp = now()->toIso8601ZuluString();
        $message = "{$member->id}:{$timestamp}";

        $hash = hash_hmac('sha256', $message, $gym->scanner_secret_key);

        return [
            'member_id' => $member->id,
            'timestamp' => $timestamp,
            'hash' => $hash,
            'gym_id' => $gym->id,
        ];
    }

    /**
     * Validiert einen QR-Code
     */
    public function validateQrCode(array $qrData, string $gymId): array
    {
        try {
            $gym = Gym::findOrFail($gymId);

            // Zeitstempel prüfen
            $qrTime = Carbon::parse($qrData['timestamp']);
            $minutesOld = $qrTime->diffInMinutes(now());

            if ($minutesOld > $this->qrCodeValidityMinutes) {
                return [
                    'valid' => false,
                    'message' => 'QR-Code abgelaufen',
                ];
            }

            // Hash validieren (prüft beide Keys bei Rotation)
            if (! $gym->validateHash(
                $qrData['member_id'],
                $qrData['timestamp'],
                $qrData['hash']
            )) {
                return [
                    'valid' => false,
                    'message' => 'Ungültiger Hash',
                ];
            }

            // Mitgliedschaft prüfen
            $member = Member::find($qrData['member_id']);
            if (! $member || ! $member->activeMembership()) {
                return [
                    'valid' => false,
                    'message' => 'Keine aktive Mitgliedschaft',
                ];
            }

            return [
                'valid' => true,
                'message' => 'Zugang gewährt',
                'member' => $member,
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Validierungsfehler: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Verifies a scanned code against the signing key of the member's own
     * location, whichever location of the organisation that is.
     *
     * The device only holds its own SECRET_KEY, so it cannot verify a code
     * signed by a sibling location. This does that server-side: the member is
     * looked up across the organisation and the hash is checked against the key
     * of the gym the member actually belongs to.
     *
     * A valid signature only proves the code is genuine — it is not permission
     * to enter. That decision stays with verify-membership.
     *
     * @param  array<int>  $organizationGymIds  Locations the scanner may look in.
     * @return bool True only for a genuine, unexpired code of a known member.
     */
    public function verifyMemberHash(
        string $memberId,
        array $organizationGymIds,
        string $hash,
        ?string $timestamp = null
    ): bool {
        if ($organizationGymIds === [] || $hash === '') {
            return false;
        }

        // The key belongs to the member's home location, not to the scanned
        // one — an ID outside the organisation never resolves and reads as
        // invalid, exactly like a forged hash.
        $gym = Member::whereIn('gym_id', $organizationGymIds)
            ->whereKey($memberId)
            ->first()
            ?->gym;

        $secretKey = $gym?->getCurrentScannerKey();

        if (! $secretKey) {
            return false;
        }

        // A rolling code carries no timestamp: it is bound to a TOTP time step
        // instead, and the window itself limits how long it stays valid.
        if ($timestamp === null) {
            return $this->matchesRollingHash($memberId, $gym, $hash);
        }

        if (! $this->isTimestampFresh($timestamp)) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', "{$memberId}:{$timestamp}", $secretKey),
            $hash
        );
    }

    /**
     * Checks a TOTP hash against the current time step and the tolerated
     * previous ones, mirroring the device-side check in validators.py.
     *
     * Answers authenticity only. Replay protection stays on the device
     * (validators.py), which registers a code once it has been confirmed —
     * including one verified here.
     */
    private function matchesRollingHash(string $memberId, Gym $gym, string $hash): bool
    {
        $interval = $gym->rolling_qr_interval ?: 3;
        $tolerance = (int) ($gym->rolling_qr_tolerance_windows ?? 1);
        $currentStep = (int) floor(time() / $interval);

        for ($offset = 0; $offset <= $tolerance; $offset++) {
            $expected = hash_hmac(
                'sha256',
                $memberId.':'.($currentStep - $offset),
                $gym->getCurrentScannerKey()
            );

            if (hash_equals($expected, $hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A static code stays valid for the configured window. An unparsable or
     * future-dated timestamp counts as invalid rather than fresh.
     */
    private function isTimestampFresh(string $timestamp): bool
    {
        try {
            $scannedAt = Carbon::parse($timestamp);
        } catch (\Exception) {
            return false;
        }

        if ($scannedAt->isFuture()) {
            // Small clock drift between the issuing server and this one is
            // normal; a code dated well ahead is not.
            return $scannedAt->diffInMinutes(now(), true) <= 1;
        }

        return $scannedAt->diffInMinutes(now(), true) <= $this->qrCodeValidityMinutes;
    }

    public function validateNfcCard(string $cardId): array
    {
        $accessConfig = MemberAccessConfig::where('nfc_uid', $cardId)->first();
        $member = $accessConfig?->member;

        return [
            'valid' => $member ? true : false,
            'member_id' => $member?->id,
            'scan_type' => 'nfc_card',
            'message' => 'Zugang gewährt',
        ];
    }
}
