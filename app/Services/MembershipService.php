<?php

namespace App\Services;

use App\Events\ContractWithdrawn;
use App\Mail\Dispatching\MemberMailDispatcher;
use App\Mail\WithdrawalConfirmationMail;
use App\Models\Membership;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Membership lifecycle operations that are shared between the admin area and
 * the member-facing PWA.
 */
class MembershipService
{
    /**
     * Withdrawal period under § 356a BGB, in days.
     */
    public const WITHDRAWAL_PERIOD_DAYS = 14;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly MemberMailDispatcher $mailDispatcher,
    ) {}

    /**
     * Checks whether a membership can still be withdrawn under § 356a BGB.
     *
     * @return array{eligible: bool, reason: string|null}
     */
    public function checkWithdrawalEligibility(Membership $membership): array
    {
        // Only paid memberships can be withdrawn
        if ($membership->is_free_trial) {
            return $this->ineligible('Kostenlose Mitgliedschaften können nicht widerrufen werden.');
        }

        // Already withdrawn?
        if ($membership->withdrawn_at) {
            return $this->ineligible('Diese Mitgliedschaft wurde bereits widerrufen.');
        }

        // Already cancelled?
        if ($membership->status === 'cancelled') {
            return $this->ineligible('Gekündigte Verträge können nicht widerrufen werden.');
        }

        // Only active or pending memberships
        if (! in_array($membership->status, ['active', 'pending'])) {
            return $this->ineligible('Diese Mitgliedschaft kann nicht widerrufen werden.');
        }

        $deadline = $this->withdrawalDeadline($membership);

        if (! $deadline) {
            return $this->ineligible('Vertragsstartdatum konnte nicht ermittelt werden.');
        }

        if (now()->isAfter($deadline)) {
            return $this->ineligible(
                'Die 14-tägige Widerrufsfrist ist bereits abgelaufen (Fristende: '.
                $deadline->format('d.m.Y H:i').').'
            );
        }

        return [
            'eligible' => true,
            'reason' => null,
        ];
    }

    /**
     * End of the 14-day withdrawal period, or null when the contract start
     * date cannot be determined.
     */
    public function withdrawalDeadline(Membership $membership): ?Carbon
    {
        $contractStartDate = $membership->contract_start_date;

        if (! $contractStartDate) {
            return null;
        }

        return Carbon::parse($contractStartDate)
            ->addDays(self::WITHDRAWAL_PERIOD_DAYS)
            ->endOfDay();
    }

    /**
     * Withdraws a membership under § 356a BGB.
     *
     * Voids pending payments, starts a refund where money was already
     * collected, ends a linked free trial period and sends the acknowledgement
     * of receipt. Eligibility is expected to have been checked by the caller —
     * the admin area can force a withdrawal past the deadline.
     *
     * @param  string  $source  Origin of the withdrawal, used for logging and
     *                          the free trial's expiry metadata.
     * @param  string|null  $note  Optional note appended to the membership.
     * @param  bool  $dispatchEvent  Whether to notify gym staff.
     * @return float The refunded amount.
     */
    public function withdraw(
        Membership $membership,
        ?string $confirmationEmail,
        string $source,
        ?string $note = null,
        bool $dispatchEvent = false,
    ): float {
        $member = $membership->member;

        $refundAmount = DB::transaction(function () use (
            $membership,
            $confirmationEmail,
            $source,
            $note,
            $member,
        ): float {
            // Void pending payments and start a refund if needed
            $refundAmount = $this->paymentService->handleWithdrawalPayments($membership);

            $attributes = [
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
                'withdrawal_confirmation_sent_to' => $confirmationEmail,
                'withdrawal_refund_amount' => $refundAmount,
            ];

            if ($note !== null) {
                $attributes['notes'] = $this->appendNote($membership->notes, $note);
            }

            $membership->update($attributes);

            $this->expireLinkedFreeTrial($membership, $source);

            // Send the acknowledgement of receipt by mail (§ 356a BGB).
            // IMPORTANT: it may only confirm receipt, never that the
            // withdrawal is "effective".
            $this->mailDispatcher->sendToAddress(
                $member,
                new WithdrawalConfirmationMail(
                    $member,
                    $membership->fresh(),
                    $member->gym,
                    [
                        'withdrawal_date' => now()->format('d.m.Y'),
                        'withdrawal_time' => now()->format('H:i'),
                        'refund_amount' => $refundAmount,
                    ]
                ),
                $confirmationEmail,
            );

            return $refundAmount;
        });

        // Notify gym staff outside the transaction
        if ($dispatchEvent) {
            ContractWithdrawn::dispatch($member, $membership->fresh(), $member->gym, $refundAmount);
        }

        Log::info('Contract withdrawn', [
            'member_id' => $membership->member_id,
            'membership_id' => $membership->id,
            'source' => $source,
            'refund_amount' => $refundAmount,
        ]);

        return $refundAmount;
    }

    /**
     * A linked free trial period belongs to the withdrawn contract and must end
     * as well, otherwise the member keeps access through it.
     */
    private function expireLinkedFreeTrial(Membership $membership, string $source): void
    {
        $freeTrial = $membership->linkedFreeMembership;

        if (! $freeTrial || $freeTrial->status === 'expired') {
            return;
        }

        $freeTrial->update([
            'notes' => $this->appendNote(
                $freeTrial->notes,
                'Gratis-Testzeitraum beendet durch Widerruf am '.now()->format('d.m.Y H:i'),
            ),
        ]);

        $freeTrial->markAsExpired($source);
    }

    private function appendNote(?string $existing, string $note): string
    {
        return ($existing ? $existing."\n" : '').$note;
    }

    /**
     * @return array{eligible: false, reason: string}
     */
    private function ineligible(string $reason): array
    {
        return [
            'eligible' => false,
            'reason' => $reason,
        ];
    }
}
