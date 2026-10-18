<?php

namespace App\Services;

use App\Models\GymScanner;
use App\Models\Member;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Issues and checks the guest service QR codes.
 *
 * Two variants, both checked by the server (the scanner forwards the raw
 * code), since a guest's entitlements live on the server anyway:
 *
 * - "rolling": shown in the guest app, signed per time step and valid for a
 *   minute at most, so a screenshot is useless soon after.
 * - "pass": stored in the wallet pass, carries a random token of which only
 *   a hash is kept. Issuing a new pass or revoking it invalidates the old one.
 */
class GuestQrCodeService
{
    public const ROLLING_INTERVAL_SECONDS = 30;

    /**
     * Previous time steps that are still accepted, covering clock drift and
     * the time between rendering and scanning.
     */
    public const ROLLING_TOLERANCE_WINDOWS = 1;

    /**
     * A reader pushes the same code several times while it is held in front
     * of it. Within this window the same device may repeat a code; anywhere
     * else, a code already used counts as a replay.
     */
    public const REPLAY_GRACE_SECONDS = 15;

    /**
     * Current rolling code for the guest app.
     *
     * @return array{qr_code: string, refresh_in: int}
     */
    public function rollingCode(Member $member): array
    {
        $step = $this->currentStep();

        return [
            'qr_code' => json_encode([
                'type' => 'guest_service',
                'variant' => 'rolling',
                'mid' => (string) $member->id,
                'hash' => $this->rollingHash($member, $step),
            ]),
            'refresh_in' => self::ROLLING_INTERVAL_SECONDS - (now()->getTimestamp() % self::ROLLING_INTERVAL_SECONDS),
        ];
    }

    /**
     * Issue the code for a new wallet pass. Any earlier pass stops working.
     */
    public function issuePassCode(Member $member): string
    {
        $token = Str::random(40);

        $member->getOrCreateAccessConfig()->forceFill([
            'guest_pass_token_hash' => hash('sha256', $token),
        ])->save();

        return json_encode([
            'type' => 'guest_service',
            'variant' => 'pass',
            'mid' => (string) $member->id,
            'token' => $token,
        ]);
    }

    public function revokePass(Member $member): void
    {
        $member->accessConfig?->forceFill(['guest_pass_token_hash' => null])->save();
    }

    public function hasPass(Member $member): bool
    {
        return ! empty($member->accessConfig?->guest_pass_token_hash);
    }

    /**
     * Check a scanned guest code.
     *
     * @return string|null null when the code is genuine, otherwise the reason
     */
    public function verify(Member $member, ?string $scannedCode, GymScanner $scanner): ?string
    {
        $data = json_decode((string) $scannedCode, true);

        if (! is_array($data) || ($data['type'] ?? null) !== 'guest_service' || (string) ($data['mid'] ?? '') !== (string) $member->id) {
            return 'Gast-QR-Code ungültig';
        }

        return match ($data['variant'] ?? null) {
            'rolling' => $this->verifyRolling($member, (string) ($data['hash'] ?? ''), $scanner),
            'pass' => $this->verifyPass($member, (string) ($data['token'] ?? '')),
            default => 'Veralteter Gast-QR-Code, bitte in der App neu laden',
        };
    }

    private function verifyRolling(Member $member, string $hash, GymScanner $scanner): ?string
    {
        if ($hash === '') {
            return 'Gast-QR-Code ungültig';
        }

        $step = $this->currentStep();
        $matches = false;

        for ($offset = 0; $offset <= self::ROLLING_TOLERANCE_WINDOWS; $offset++) {
            if (hash_equals($this->rollingHash($member, $step - $offset), $hash)) {
                $matches = true;
                break;
            }
        }

        if (! $matches) {
            return 'Gast-QR-Code abgelaufen, bitte in der App neu laden';
        }

        $key = 'guest-qr-used:'.hash('sha256', $member->id.':'.$hash);
        $ttl = (self::ROLLING_TOLERANCE_WINDOWS + 1) * self::ROLLING_INTERVAL_SECONDS;

        if (Cache::add($key, ['scanner_id' => $scanner->id, 'at' => now()->getTimestamp()], $ttl)) {
            return null;
        }

        $firstUse = Cache::get($key);
        $repeatAtSameDevice = ($firstUse['scanner_id'] ?? null) === $scanner->id
            && now()->getTimestamp() - ($firstUse['at'] ?? 0) <= self::REPLAY_GRACE_SECONDS;

        return $repeatAtSameDevice ? null : 'Gast-QR-Code bereits verwendet';
    }

    private function verifyPass(Member $member, string $token): ?string
    {
        $storedHash = $member->accessConfig?->guest_pass_token_hash;

        if ($token === '' || ! $storedHash || ! hash_equals($storedHash, hash('sha256', $token))) {
            return 'Wallet-Pass ungültig oder gesperrt';
        }

        return null;
    }

    private function rollingHash(Member $member, int $step): string
    {
        // Own prefix: can never collide with a member access code.
        return hash_hmac(
            'sha256',
            'guest_service:rolling:'.$member->id.':'.$step,
            (string) $member->gym->getCurrentScannerKey()
        );
    }

    private function currentStep(): int
    {
        return intdiv(now()->getTimestamp(), self::ROLLING_INTERVAL_SECONDS);
    }
}
