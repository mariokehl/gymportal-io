<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberCreditLedger;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Manages a member's stored credit ("Guthaben") in a dedicated, encrypted and
 * tamper-evident ledger.
 *
 * Security model:
 *  - Amounts are kept as integer cents (no floats) and stored encrypted.
 *  - Every entry is signed with an HMAC over its own values plus the previous
 *    entry's hash, forming a chain. Altering, deleting, inserting or reordering
 *    any row breaks the chain, which {@see verify()} detects. Without the secret
 *    key an attacker cannot forge a valid hash, so a raw-database change to an
 *    amount (e.g. 3 € -> 300 €) is rejected rather than trusted.
 */
class CreditLedgerService
{
    /**
     * Genesis value used as prev_hash for the very first entry of a member.
     */
    private const GENESIS_HASH = 'genesis';

    /**
     * Current, verified balance of a member in integer cents.
     *
     * @throws RuntimeException when the ledger integrity check fails.
     */
    public function getBalance(Member $member): int
    {
        $entries = $this->chain($member);

        if ($entries->isEmpty()) {
            return 0;
        }

        $this->assertChainValid($member, $entries);

        return $this->decryptCents($entries->last()->encrypted_balance_after);
    }

    /**
     * Verified balance formatted as a German currency string, e.g. "3,00 €".
     */
    public function getFormattedBalance(Member $member): string
    {
        return $this->formatCents($this->getBalance($member));
    }

    /**
     * Verify the whole HMAC chain for a member without exposing the balance.
     */
    public function verify(Member $member): bool
    {
        $entries = $this->chain($member);

        if ($entries->isEmpty()) {
            return true;
        }

        try {
            $this->assertChainValid($member, $entries);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Add credit to a member's balance (top-up, refund or manual adjustment).
     */
    public function credit(
        Member $member,
        int $amountCents,
        string $type = MemberCreditLedger::TYPE_TOPUP,
        string $description = '',
        ?Payment $payment = null,
        ?User $createdBy = null,
    ): MemberCreditLedger {
        if ($amountCents <= 0) {
            throw new RuntimeException('Credit amount must be positive.');
        }

        return $this->writeEntry($member, $type, $amountCents, $description, $payment, $createdBy);
    }

    /**
     * Credit the balance from a paid credit-top-up payment.
     *
     * Used when a Mollie top-up is confirmed via webhook: only now does the
     * amount become real credit. Idempotent — a payment already credited (or one
     * that is not a paid top-up) is skipped, so repeated webhooks are safe.
     */
    public function creditFromPaidTopup(Payment $payment): ?MemberCreditLedger
    {
        $member = $payment->member;
        $metadata = $payment->metadata ?? [];

        if (! $payment->is_credit_topup
            || $payment->status !== 'paid'
            || $member === null
            || ! empty($metadata['credit_credited'])) {
            return null;
        }

        $amountCents = $this->toCents((string) $payment->amount);

        if ($amountCents <= 0) {
            return null;
        }

        return DB::transaction(function () use ($member, $amountCents, $payment, $metadata) {
            $entry = $this->credit(
                member: $member,
                amountCents: $amountCents,
                type: MemberCreditLedger::TYPE_TOPUP,
                description: $payment->description ?? 'Guthaben-Aufladung',
                payment: $payment,
            );

            $metadata['credit_credited'] = true;
            $metadata['credit_ledger_id'] = $entry->id;
            $payment->update(['metadata' => $metadata]);

            return $entry;
        });
    }

    /**
     * Redeem available credit against a payment, up to the requested amount.
     *
     * Never overdraws: only the balance actually available is redeemed. Returns
     * the number of cents actually deducted, so the caller can settle the rest
     * over the regular payment method.
     */
    public function redeem(Member $member, int $requestedCents, ?Payment $payment = null, ?User $createdBy = null): int
    {
        if ($requestedCents <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($member, $requestedCents, $payment, $createdBy) {
            // Lock the member's chain so concurrent redeem/credit calls serialise.
            $entries = $this->chain($member, lock: true);

            $balance = $entries->isEmpty()
                ? 0
                : $this->decryptCents($this->assertChainValid($member, $entries)->last()->encrypted_balance_after);

            $redeemCents = min($requestedCents, $balance);

            if ($redeemCents <= 0) {
                return 0;
            }

            $this->appendEntry(
                member: $member,
                entries: $entries,
                type: MemberCreditLedger::TYPE_REDEMPTION,
                deltaCents: -$redeemCents,
                description: $payment?->description
                    ? 'Guthaben-Einlösung: '.$payment->description
                    : 'Guthaben-Einlösung',
                payment: $payment,
                createdBy: $createdBy,
            );

            return $redeemCents;
        });
    }

    /**
     * Apply available credit to a payment, credit-first.
     *
     * Redeems up to the payment's amount. If the credit fully covers it, the
     * payment is marked paid (payment method "credit"). Otherwise a partial
     * redemption is recorded and the remaining cents are returned so the caller
     * can collect them over the regular payment method.
     *
     * Idempotent: a payment already flagged as credit-redeemed is skipped.
     *
     * @return array{fully_covered: bool, redeemed_cents: int, remaining_cents: int}
     */
    public function settlePayment(Payment $payment, ?User $createdBy = null): array
    {
        $member = $payment->member;
        $amountCents = $this->toCents((string) $payment->amount);

        $metadata = $payment->metadata ?? [];

        // Already settled with credit before (e.g. a retry): do not double-redeem.
        if (! empty($metadata['credit_redeemed'])) {
            $redeemed = (int) ($metadata['credit_redeemed_cents'] ?? 0);

            return [
                'fully_covered' => $redeemed >= $amountCents,
                'redeemed_cents' => $redeemed,
                'remaining_cents' => max(0, $amountCents - $redeemed),
            ];
        }

        if ($member === null || $amountCents <= 0) {
            return ['fully_covered' => false, 'redeemed_cents' => 0, 'remaining_cents' => $amountCents];
        }

        $redeemedCents = $this->redeem($member, $amountCents, $payment, $createdBy);

        if ($redeemedCents <= 0) {
            return ['fully_covered' => false, 'redeemed_cents' => 0, 'remaining_cents' => $amountCents];
        }

        $remainingCents = $amountCents - $redeemedCents;
        $balanceAfter = $this->getBalance($member);

        // Reference the actual ledger row that recorded this redemption.
        $ledgerId = MemberCreditLedger::where('payment_id', $payment->id)
            ->where('type', MemberCreditLedger::TYPE_REDEMPTION)
            ->latest('id')
            ->value('id');

        $metadata['credit_redeemed'] = true;
        $metadata['credit_redeemed_cents'] = $redeemedCents;
        $metadata['credit_balance_after_cents'] = $balanceAfter;
        $metadata['original_amount_cents'] = $amountCents;
        $metadata['credit_ledger_id'] = $ledgerId;

        $note = 'Guthaben verrechnet: '.$this->formatCents($redeemedCents)
            .' (Restguthaben: '.$this->formatCents($balanceAfter).')';

        if ($remainingCents <= 0) {
            // Fully covered by credit.
            $payment->update([
                'status' => 'paid',
                'paid_date' => now(),
                'payment_method' => 'credit',
                'metadata' => $metadata,
                'notes' => ($payment->notes ? $payment->notes."\n" : '').$note,
            ]);
        } else {
            // Partially covered: reduce the amount to what the payment method
            // still has to collect. The redeemed part lives in the ledger, and
            // the original amount is preserved in metadata for the history.
            $payment->update([
                'amount' => $remainingCents / 100,
                'metadata' => $metadata,
                'notes' => ($payment->notes ? $payment->notes."\n" : '').$note,
            ]);
        }

        return [
            'fully_covered' => $remainingCents <= 0,
            'redeemed_cents' => $redeemedCents,
            'remaining_cents' => $remainingCents,
        ];
    }

    /**
     * Parse a user-supplied amount (e.g. "300,00", "49.90", "1.234,50") to
     * integer cents without going through a binary float.
     */
    public function toCents(string|int|float $amount): int
    {
        $normalized = trim((string) $amount);

        // Strip currency symbols/whitespace, keep digits, separators and sign.
        $normalized = preg_replace('/[^0-9,.\-]/', '', $normalized) ?? '';

        if ($normalized === '' || $normalized === '-') {
            return 0;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            // The last-occurring separator is the decimal separator.
            $decimalSep = strrpos($normalized, ',') > strrpos($normalized, '.') ? ',' : '.';
            $thousandSep = $decimalSep === ',' ? '.' : ',';
            $normalized = str_replace($thousandSep, '', $normalized);
            $normalized = str_replace($decimalSep, '.', $normalized);
        } elseif ($hasComma) {
            // Single comma: treat as decimal separator (German input).
            $normalized = str_replace(',', '.', $normalized);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $sign = str_starts_with($whole, '-') ? -1 : 1;
        $whole = ltrim($whole, '+-');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        $cents = ((int) ($whole === '' ? '0' : $whole)) * 100 + (int) $fraction;

        return $sign * $cents;
    }

    /**
     * Format integer cents as a German currency string, e.g. "1.234,50 €".
     */
    public function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    /**
     * Ordered ledger entries for a member (oldest first), optionally locked.
     *
     * @return Collection<int, MemberCreditLedger>
     */
    private function chain(Member $member, bool $lock = false)
    {
        $query = MemberCreditLedger::where('member_id', $member->id)->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Validate the full HMAC chain. Throws on any tampering.
     *
     * @param  Collection<int, MemberCreditLedger>  $entries
     * @return Collection<int, MemberCreditLedger>
     */
    private function assertChainValid(Member $member, $entries)
    {
        $prevHash = self::GENESIS_HASH;
        $prevBalance = 0;

        foreach ($entries as $entry) {
            $amount = $this->decryptCents($entry->encrypted_amount);
            $balanceAfter = $this->decryptCents($entry->encrypted_balance_after);

            $expectedHash = $this->computeHash(
                gymId: (int) $entry->gym_id,
                memberId: (int) $entry->member_id,
                sequence: (int) $entry->id,
                type: (string) $entry->type,
                amountCents: $amount,
                balanceAfterCents: $balanceAfter,
                prevHash: $prevHash,
            );

            $chainOk = hash_equals($expectedHash, (string) $entry->entry_hash)
                && hash_equals($prevHash, (string) ($entry->prev_hash ?? self::GENESIS_HASH))
                && $balanceAfter === $prevBalance + $amount
                && $balanceAfter >= 0;

            if (! $chainOk) {
                Log::critical('Member credit ledger integrity check failed', [
                    'member_id' => $member->id,
                    'entry_id' => $entry->id,
                ]);

                throw new RuntimeException("Credit ledger integrity check failed for member #{$member->id}.");
            }

            $prevHash = (string) $entry->entry_hash;
            $prevBalance = $balanceAfter;
        }

        return $entries;
    }

    /**
     * Append a signed entry, recomputing the balance from the verified chain.
     */
    private function writeEntry(
        Member $member,
        string $type,
        int $deltaCents,
        string $description,
        ?Payment $payment,
        ?User $createdBy,
    ): MemberCreditLedger {
        return DB::transaction(function () use ($member, $type, $deltaCents, $description, $payment, $createdBy) {
            $entries = $this->chain($member, lock: true);

            if ($entries->isNotEmpty()) {
                $this->assertChainValid($member, $entries);
            }

            return $this->appendEntry($member, $entries, $type, $deltaCents, $description, $payment, $createdBy);
        });
    }

    /**
     * Insert a new ledger row and stamp it into the HMAC chain.
     *
     * Assumes the caller already validated $entries and holds a lock.
     *
     * @param  Collection<int, MemberCreditLedger>  $entries
     */
    private function appendEntry(
        Member $member,
        $entries,
        string $type,
        int $deltaCents,
        string $description,
        ?Payment $payment,
        ?User $createdBy,
    ): MemberCreditLedger {
        $prevEntry = $entries->last();
        $prevHash = $prevEntry?->entry_hash ?? self::GENESIS_HASH;
        $prevBalance = $prevEntry ? $this->decryptCents($prevEntry->encrypted_balance_after) : 0;

        $balanceAfter = $prevBalance + $deltaCents;

        if ($balanceAfter < 0) {
            throw new RuntimeException('Credit balance cannot become negative.');
        }

        $entry = new MemberCreditLedger([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'payment_id' => $payment?->id,
            'created_by' => $createdBy?->id,
            'type' => $type,
            'encrypted_amount' => Crypt::encryptString((string) $deltaCents),
            'encrypted_balance_after' => Crypt::encryptString((string) $balanceAfter),
            'prev_hash' => $prevHash,
            // Temporary placeholder; the hash binds the row id, so we sign after insert.
            'entry_hash' => str_repeat('0', 64),
            'description' => $description,
        ]);
        $entry->save();

        $entry->entry_hash = $this->computeHash(
            gymId: (int) $member->gym_id,
            memberId: (int) $member->id,
            sequence: (int) $entry->id,
            type: $type,
            amountCents: $deltaCents,
            balanceAfterCents: $balanceAfter,
            prevHash: $prevHash,
        );

        // Persist the final hash without tripping the immutable-update guard:
        // this is the initial signing of a freshly inserted row.
        MemberCreditLedger::withoutEvents(function () use ($entry): void {
            DB::table($entry->getTable())
                ->where('id', $entry->id)
                ->update(['entry_hash' => $entry->entry_hash]);
        });

        return $entry;
    }

    /**
     * Deterministic HMAC signature over an entry's canonical representation.
     */
    private function computeHash(
        int $gymId,
        int $memberId,
        int $sequence,
        string $type,
        int $amountCents,
        int $balanceAfterCents,
        string $prevHash,
    ): string {
        $message = implode('|', [
            $gymId,
            $memberId,
            $sequence,
            $type,
            $amountCents,
            $balanceAfterCents,
            $prevHash,
        ]);

        return hash_hmac('sha256', $message, $this->hmacKey());
    }

    private function decryptCents(string $ciphertext): int
    {
        return (int) Crypt::decryptString($ciphertext);
    }

    private function hmacKey(): string
    {
        return config('credit.hmac_key') ?: config('app.key');
    }
}
