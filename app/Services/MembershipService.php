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
     * Checks whether a membership can be paused.
     *
     * @return array{eligible: bool, reason: string|null}
     */
    public function checkPauseEligibility(Membership $membership): array
    {
        if ($membership->status !== 'active') {
            return $this->ineligible('Nur aktive Mitgliedschaften können pausiert werden.');
        }

        return [
            'eligible' => true,
            'reason' => null,
        ];
    }

    /**
     * Checks whether a paused membership can be resumed.
     *
     * @return array{eligible: bool, reason: string|null}
     */
    public function checkResumeEligibility(Membership $membership): array
    {
        if ($membership->status !== 'paused') {
            return $this->ineligible('Nur pausierte Mitgliedschaften können wieder aufgenommen werden.');
        }

        return [
            'eligible' => true,
            'reason' => null,
        ];
    }

    /**
     * Schedules a pause period for a membership.
     *
     * The status only switches to 'paused' when the pause starts today. A pause
     * scheduled for a later date leaves the membership active until
     * memberships:update-statuses picks it up on the start date.
     *
     * The contract end date is pushed back by the pause duration so the member
     * keeps the full contract term. Eligibility is expected to have been checked
     * by the caller.
     *
     * @param  string|null  $reason  Optional reason appended to the notes.
     */
    public function pause(
        Membership $membership,
        Carbon|string $pauseStartDate,
        Carbon|string $pauseEndDate,
        ?string $reason = null,
    ): Membership {
        $pauseStart = Carbon::parse($pauseStartDate)->startOfDay();
        $pauseEnd = Carbon::parse($pauseEndDate)->startOfDay();

        return DB::transaction(function () use ($membership, $pauseStart, $pauseEnd, $reason): Membership {
            $startsToday = $pauseStart->isToday();

            $attributes = [
                'pause_start_date' => $pauseStart,
                'pause_end_date' => $pauseEnd,
            ];

            // A pause that starts later leaves the membership active for now
            if ($startsToday) {
                $attributes['status'] = 'paused';
            }

            if ($reason) {
                $attributes['notes'] = $this->appendNote(
                    $membership->notes,
                    ($startsToday
                        ? 'Pausiert am '.now()->format('d.m.Y')
                        : 'Pausierung ab '.$pauseStart->format('d.m.Y').' eingeplant am '.now()->format('d.m.Y')
                    ).': '.$reason,
                );
            }

            // Extend the contract end date by the pause duration
            if ($membership->end_date) {
                $pauseDays = $pauseStart->diffInDays($pauseEnd);

                $attributes['end_date'] = Carbon::parse($membership->end_date)->addDays($pauseDays);
            }

            $membership->update($attributes);

            Log::info($startsToday ? 'Membership paused' : 'Membership pause scheduled', [
                'member_id' => $membership->member_id,
                'membership_id' => $membership->id,
                'pause_start_date' => $pauseStart->toDateString(),
                'pause_end_date' => $pauseEnd->toDateString(),
            ]);

            return $membership;
        });
    }

    /**
     * Resumes a paused membership as of today.
     *
     * When the membership is resumed before the scheduled pause end, the unused
     * pause days are subtracted from the contract end date again. Eligibility is
     * expected to have been checked by the caller.
     */
    public function resume(Membership $membership): Membership
    {
        return DB::transaction(function () use ($membership): Membership {
            $resumeDate = now()->startOfDay();

            $attributes = [
                'status' => 'active',
                'pause_end_date' => $resumeDate,
                'notes' => $this->appendNote(
                    $membership->notes,
                    'Wieder aufgenommen am '.now()->format('d.m.Y'),
                ),
            ];

            // Give back the end date extension for the unused pause days
            $originalPauseEnd = $membership->pause_end_date;

            if ($originalPauseEnd && $membership->end_date && $resumeDate->isBefore($originalPauseEnd)) {
                $unusedPauseDays = $resumeDate->diffInDays($originalPauseEnd);

                $attributes['end_date'] = Carbon::parse($membership->end_date)->subDays($unusedPauseDays);
            }

            $membership->update($attributes);

            Log::info('Membership resumed', [
                'member_id' => $membership->member_id,
                'membership_id' => $membership->id,
                'resumed_at' => $resumeDate->toDateString(),
            ]);

            return $membership;
        });
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
