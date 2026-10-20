<?php

namespace App\Services;

use App\Models\GuestProduct;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberAccessLog;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Books what a guest bought onto their access config and redeems it at the
 * scanner: solarium minutes, visit card entries and the day pass.
 *
 * These are entitlements in units (minutes, entries, days), deliberately kept
 * apart from the monetary credit ("Guthaben") in the member credit ledger.
 */
class GuestEntitlementService
{
    /**
     * Current entitlements of a guest, as shown in the guest app.
     *
     * @return array{solarium_minutes: int, visit_card_entries: int, day_pass_valid_until: string|null}
     */
    public function summary(Member $member): array
    {
        $config = $member->accessConfig;

        if (! $config) {
            return [
                'solarium_minutes' => 0,
                'visit_card_entries' => 0,
                'day_pass_valid_until' => null,
            ];
        }

        return [
            'solarium_minutes' => $config->solarium_enabled ? (int) $config->solarium_minutes : 0,
            'visit_card_entries' => $config->visit_card_enabled ? (int) $config->visit_card_entries : 0,
            'day_pass_valid_until' => $config->isDayPassValid()
                ? $config->day_pass_valid_until->toDateString()
                : null,
        ];
    }

    /**
     * Grant a purchased product. A day pass is valid for the day of purchase.
     */
    public function grant(Member $member, GuestProduct $product, ?Payment $payment = null): void
    {
        DB::transaction(function () use ($member, $product, $payment) {
            $config = $this->lockedConfig($member);

            match ($product->type) {
                GuestProduct::TYPE_SOLARIUM_MINUTES => $config->update([
                    'solarium_enabled' => true,
                    'solarium_minutes' => (int) $config->solarium_minutes + $product->quantity,
                ]),
                GuestProduct::TYPE_VISIT_CARD => $config->update([
                    'visit_card_enabled' => true,
                    'visit_card_entries' => (int) $config->visit_card_entries + $product->quantity,
                ]),
                GuestProduct::TYPE_DAY_PASS => $config->update([
                    'day_pass_enabled' => true,
                    'day_pass_valid_until' => now()->toDateString(),
                ]),
            };

            $this->log($member, MemberAccessLog::ACTION_CREDIT_ADDED, $this->serviceFor($product), [
                'product_type' => $product->type,
                'quantity' => $product->quantity,
                'payment_id' => $payment?->id,
                'reason' => 'guest_purchase',
            ]);
        });
    }

    /**
     * Take back a refunded or charged-back purchase. Units the guest has
     * already used cannot be recovered, so the deduction stops at zero.
     */
    public function revoke(Member $member, GuestProduct $product, Payment $payment): void
    {
        DB::transaction(function () use ($member, $product, $payment) {
            $config = $this->lockedConfig($member);

            match ($product->type) {
                GuestProduct::TYPE_SOLARIUM_MINUTES => $this->deduct($config, 'solarium', $product->quantity),
                GuestProduct::TYPE_VISIT_CARD => $this->deduct($config, 'visit_card', $product->quantity),
                GuestProduct::TYPE_DAY_PASS => $config->update([
                    'day_pass_enabled' => false,
                    'day_pass_valid_until' => null,
                ]),
            };

            $this->log($member, MemberAccessLog::ACTION_CREDIT_CONSUMED, $this->serviceFor($product), [
                'product_type' => $product->type,
                'quantity' => $product->quantity,
                'payment_id' => $payment->id,
                'reason' => 'guest_purchase_revoked',
            ]);
        });

        Log::info('Guest purchase revoked', [
            'member_id' => $member->id,
            'payment_id' => $payment->id,
            'product_type' => $product->type,
        ]);
    }

    /**
     * Redeem a guest service QR code at a scanner.
     *
     * At the solarium, remaining solarium minutes open the door; the minutes
     * themselves are redeemed afterwards in the guest app. Everywhere else a
     * valid day pass grants entry, otherwise one visit card entry is used.
     *
     * @return array{access_allowed: bool, message: string, entitlements: array}
     */
    public function redeemAtScanner(GymScanner $scanner, Member $member): array
    {
        return DB::transaction(function () use ($scanner, $member) {
            $config = $this->lockedConfig($member);

            if ($scanner->isSolarium()) {
                if (! $member->hasReachedAge(GuestProduct::SOLARIUM_MIN_AGE)) {
                    return $this->scanResult($member, false, 'Solarium erst ab '.GuestProduct::SOLARIUM_MIN_AGE.' Jahren');
                }

                $allowed = $config->solarium_enabled && $config->solarium_minutes > 0;

                return $this->scanResult($member, $allowed, $allowed
                    ? 'Zugang zum Solarium gewährt'
                    : 'Keine Solarium-Minuten vorhanden');
            }

            if ($config->isDayPassValid()) {
                return $this->scanResult($member, true, 'Zugang gewährt (Tageskarte)');
            }

            if ($config->visit_card_enabled && $config->visit_card_entries > 0) {
                $this->deduct($config, 'visit_card', 1);

                $this->log($member, MemberAccessLog::ACTION_CREDIT_CONSUMED, MemberAccessLog::SERVICE_GYM, [
                    'product_type' => GuestProduct::TYPE_VISIT_CARD,
                    'quantity' => 1,
                    'remaining' => (int) $config->visit_card_entries,
                    'scanner_device' => $scanner->device_number,
                ]);

                return $this->scanResult($member, true, 'Zugang gewährt (Mehrfachkarte)');
            }

            return $this->scanResult($member, false, 'Keine Tageskarte und keine Eintritte vorhanden');
        });
    }

    /**
     * Deduct units of a service, clamped at zero. Disables the service once
     * nothing is left.
     */
    private function deduct(MemberAccessConfig $config, string $service, int $quantity): void
    {
        $column = $service === 'solarium' ? 'solarium_minutes' : 'visit_card_entries';
        $remaining = max(0, (int) $config->{$column} - $quantity);

        $config->update([
            $column => $remaining,
            "{$service}_enabled" => $remaining > 0,
        ]);
    }

    private function lockedConfig(Member $member): MemberAccessConfig
    {
        $member->getOrCreateAccessConfig();

        return MemberAccessConfig::where('member_id', $member->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function scanResult(Member $member, bool $allowed, string $message): array
    {
        $member->unsetRelation('accessConfig');

        return [
            'access_allowed' => $allowed,
            'message' => $message,
            'entitlements' => $this->summary($member),
        ];
    }

    private function serviceFor(GuestProduct $product): string
    {
        return $product->type === GuestProduct::TYPE_SOLARIUM_MINUTES
            ? MemberAccessLog::SERVICE_SOLARIUM
            : MemberAccessLog::SERVICE_GYM;
    }

    private function log(Member $member, string $action, string $service, array $metadata): void
    {
        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => $action,
            'success' => true,
            'service' => $service,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
