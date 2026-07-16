<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ProcessMembershipPayments;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\CreditLedgerService;
use App\Services\MollieService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Covers the auto-renewal cadence of ProcessMembershipPayments.
 *
 * Renewal is triggered at the cancellation deadline (end_date + 1 day -
 * cancellation_period): once the customer can no longer cancel the current
 * period, the contract is bound to renew, so the new end_date is written on that
 * date. With a cancellation period the contract therefore renews one period
 * ahead of its nominal end_date. Each period still renews exactly once, because
 * writing the new end_date pushes the next cancellation deadline into the future.
 */
class MembershipRenewalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drive the protected renewal methods exactly like the daily cron would:
     * for each day, if shouldRenewMembership() returns true, renew once.
     */
    private function simulateDailyCron(ProcessMembershipPayments $command, Membership $membership, Carbon $from, Carbon $to): void
    {
        $shouldRenew = new ReflectionMethod($command, 'shouldRenewMembership');
        $renew = new ReflectionMethod($command, 'renewMembership');

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            Carbon::setTestNow($day);
            $membership->refresh()->load('membershipPlan');

            if ($membership->end_date === null) {
                continue; // converted to indefinite, nothing more to renew
            }

            if ($shouldRenew->invoke($command, $membership)) {
                $renew->invoke($command, $membership);
            }
        }
    }

    #[Test]
    public function monthly_renewal_advances_exactly_one_cycle_per_period(): void
    {
        // Plan: 1-month minimum term, 1-month cancellation period, monthly rollover.
        $plan = MembershipPlan::factory()->create([
            'is_free_trial_plan' => false,
            'commitment_months' => 1,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
            'auto_renew_type' => 'monthly',
        ]);

        $member = Member::factory()->create();

        // Initial term: 01.03.2026 – 31.03.2026 (end_date = start + 1 month - 1 day).
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'status' => 'active',
        ]);

        // Avoid hitting the real PaymentService/Mollie during renewMembership().
        $command = $this->mockRenewWithoutPayments();

        // Run the daily cron from the first cancellation deadline through 31.05.2026.
        $this->simulateDailyCron($command, $membership, Carbon::parse('2026-03-25'), Carbon::parse('2026-05-31'));

        Carbon::setTestNow();

        // Renewals fire on each cancellation deadline:
        //   25.03 (deadline 01.03 passed): 31.03 -> 30.04
        //   01.04 (deadline 01.04):        30.04 -> 31.05
        //   01.05 (deadline 01.05):        31.05 -> 30.06
        // The 01.06 deadline is not reached before the cron stops on 31.05.
        $this->assertSame(
            '2026-06-30',
            $membership->refresh()->end_date->toDateString(),
            'Renewal did not land on 30.06.'
        );

        // Exactly one renewal per period: 3 renewals to reach 30.06.
        $this->assertSame(
            3,
            $membership->metadata['renewal_count'] ?? 0,
            'Too many renewals: contract was renewed more than once per period.'
        );
    }

    #[Test]
    public function renews_from_the_cancellation_deadline_onwards(): void
    {
        $plan = MembershipPlan::factory()->create([
            'is_free_trial_plan' => false,
            'commitment_months' => 1,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
            'auto_renew_type' => 'monthly',
        ]);

        $membership = Membership::factory()->create([
            'member_id' => Member::factory()->create()->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
        ]);

        $command = $this->mockRenewWithoutPayments();
        $shouldRenew = new ReflectionMethod($command, 'shouldRenewMembership');
        $membership->load('membershipPlan');

        // Cancellation deadline for a 1-month period: 30.06 + 1 day - 1 month = 01.06.
        // The day before the deadline must NOT trigger a renewal.
        Carbon::setTestNow(Carbon::parse('2026-05-31'));
        $this->assertFalse($shouldRenew->invoke($command, $membership), 'Renewed before the cancellation deadline.');

        // On the cancellation deadline itself: renew.
        Carbon::setTestNow(Carbon::parse('2026-06-01'));
        $this->assertTrue($shouldRenew->invoke($command, $membership), 'Did not renew on the cancellation deadline.');

        // Still true later within the running period.
        Carbon::setTestNow(Carbon::parse('2026-06-30'));
        $this->assertTrue($shouldRenew->invoke($command, $membership), 'Did not renew on the end date.');

        Carbon::setTestNow();
    }

    #[Test]
    public function does_not_renew_a_cancelled_membership(): void
    {
        $plan = MembershipPlan::factory()->create([
            'is_free_trial_plan' => false,
            'commitment_months' => 1,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
            'auto_renew_type' => 'monthly',
        ]);

        $command = $this->mockRenewWithoutPayments();
        $shouldRenew = new ReflectionMethod($command, 'shouldRenewMembership');

        Carbon::setTestNow(Carbon::parse('2026-06-30'));

        // Cancelled membership whose period has ended must NOT renew.
        $cancelled = Membership::factory()->create([
            'member_id' => Member::factory()->create()->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-06-30',
            'status' => 'cancelled',
            'cancellation_date' => '2026-06-30',
        ])->load('membershipPlan');

        $this->assertFalse($shouldRenew->invoke($command, $cancelled), 'A cancelled membership was renewed.');

        // Still active but cancelled to the end of term (future cancellation_date):
        // must not renew once the end date is reached either.
        $cancelledToTermEnd = Membership::factory()->create([
            'member_id' => Member::factory()->create()->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-06-30',
            'status' => 'active',
            'cancellation_date' => '2026-06-30',
        ])->load('membershipPlan');

        $this->assertFalse($shouldRenew->invoke($command, $cancelledToTermEnd), 'A membership cancelled to term end was renewed.');

        Carbon::setTestNow();
    }

    /**
     * A cancellation mid-year must stop the renewal chain: no further renewals
     * happen once cancellation_date is set.
     */
    #[Test]
    public function cancellation_mid_year_stops_further_renewals(): void
    {
        $plan = MembershipPlan::factory()->create([
            'is_free_trial_plan' => false,
            'commitment_months' => 1,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
            'auto_renew_type' => 'monthly',
        ]);

        $membership = Membership::factory()->create([
            'member_id' => Member::factory()->create()->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'active',
        ]);

        $command = $this->mockRenewWithoutPayments();

        // Renew normally until April, then cancel to the end of the running term.
        $this->simulateDailyCron($command, $membership, Carbon::parse('2026-01-01'), Carbon::parse('2026-04-15'));
        $endAtCancellation = $membership->refresh()->end_date->toDateString();
        $membership->update([
            'status' => 'cancelled',
            'cancellation_date' => $endAtCancellation,
        ]);
        $renewalsBeforeCancellation = $membership->metadata['renewal_count'] ?? 0;

        // Keep the cron running for the rest of the year.
        $this->simulateDailyCron($command, $membership, Carbon::parse('2026-04-16'), Carbon::parse('2026-12-31'));

        Carbon::setTestNow();

        $this->assertSame(
            $endAtCancellation,
            $membership->refresh()->end_date->toDateString(),
            'End date advanced after cancellation.'
        );
        $this->assertSame(
            $renewalsBeforeCancellation,
            $membership->metadata['renewal_count'] ?? 0,
            'Membership was renewed after it had been cancelled.'
        );
    }

    public static function cancellationPeriodProvider(): array
    {
        return [
            '14-day cancellation period' => [14, 'days'],
            '1-month cancellation period' => [1, 'months'],
        ];
    }

    /**
     * A membership starting 01.01 renews monthly over a full year. Because the
     * renewal fires at the cancellation deadline, the contract always runs one
     * period ahead of the calendar: by the end of December the end_date has
     * already been rolled into the following January. Each month still renews
     * exactly once, independent of the cancellation period length.
     */
    #[Test]
    #[DataProvider('cancellationPeriodProvider')]
    public function renews_monthly_for_a_full_year(int $period, string $unit): void
    {
        $plan = MembershipPlan::factory()->create([
            'is_free_trial_plan' => false,
            'commitment_months' => 1,
            'cancellation_period' => $period,
            'cancellation_period_unit' => $unit,
            'auto_renew_type' => 'monthly',
        ]);

        // Initial term: 01.01.2026 – 31.01.2026.
        $membership = Membership::factory()->create([
            'member_id' => Member::factory()->create()->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => 'active',
        ]);

        $command = $this->mockRenewWithoutPayments();

        $this->simulateDailyCron($command, $membership, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-30'));

        Carbon::setTestNow();

        // Renewing one period ahead: the December deadline has already rolled the
        // contract into January 2027. That is 12 renewals over the year, and the
        // cancellation period does not change the cadence.
        $this->assertSame(
            '2027-01-31',
            $membership->refresh()->end_date->toDateString(),
            'Yearly monthly renewal did not land on 31.01.2027.'
        );
        $this->assertSame(
            12,
            $membership->metadata['renewal_count'] ?? 0,
            'Expected exactly 12 renewals, regardless of cancellation period.'
        );
    }

    /**
     * Build the command and neutralise payment creation so the test stays focused
     * on date arithmetic. createPaymentsForMembership() is protected and pulls in
     * Mollie; we stub it out via a partial mock.
     */
    private function mockRenewWithoutPayments(): ProcessMembershipPayments
    {
        $command = $this->getMockBuilder(ProcessMembershipPayments::class)
            ->setConstructorArgs([
                app(PaymentService::class),
                app(MollieService::class),
                app(CreditLedgerService::class),
            ])
            ->onlyMethods(['createPaymentsForMembership'])
            ->getMock();

        $command->method('createPaymentsForMembership')->willReturn(0);

        return $command;
    }
}
