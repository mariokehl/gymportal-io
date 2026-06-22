<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ProcessMembershipPayments;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\MollieService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Reproduces the live bug where a membership renewed one full billing cycle too
 * early: at 31.05 the end_date jumped to 31.07 instead of 30.06.
 *
 * Root cause hypothesis: shouldRenewMembership() uses the cancellation deadline
 * (end_date - cancellation_period) as the renewal trigger. With a 1-month
 * cancellation period the deadline sits ~30 days before end_date, so the contract
 * re-qualifies for renewal a whole period before it actually ends — and the
 * Carbon 3 signed diffInDays(<= 30) check fails to gate it.
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

        // Run the daily cron from the first renewal window through 31.05.2026 —
        // the day the live system jumped to 31.07 instead of 30.06.
        $this->simulateDailyCron($command, $membership, Carbon::parse('2026-03-25'), Carbon::parse('2026-05-31'));

        Carbon::setTestNow();

        // After the May period the contract must end 30.06.2026 — NOT 31.07.2026.
        $this->assertSame(
            '2026-06-30',
            $membership->refresh()->end_date->toDateString(),
            'Membership renewed one cycle too early (double renewal within a period).'
        );

        // Exactly one renewal per period: 31.03 -> 30.04 -> 31.05 -> 30.06 = 3 renewals.
        $this->assertSame(
            3,
            $membership->metadata['renewal_count'] ?? 0,
            'Too many renewals: contract was renewed more than once per period.'
        );
    }

    #[Test]
    public function does_not_renew_before_the_end_date_is_reached(): void
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

        // The cancellation deadline (30.05) must NOT trigger a renewal.
        Carbon::setTestNow(Carbon::parse('2026-05-30'));
        $this->assertFalse($shouldRenew->invoke($command, $membership), 'Renewed at cancellation deadline, far before end_date.');

        // One day before the end date: still no renewal.
        Carbon::setTestNow(Carbon::parse('2026-06-29'));
        $this->assertFalse($shouldRenew->invoke($command, $membership), 'Renewed one day too early.');

        // On the end date itself: renew.
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
     * A membership starting 01.01 must renew monthly to 31.12 over a full year —
     * exactly 12 renewals, regardless of the cancellation period. The cancellation
     * period must not influence the renewal cadence.
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

        // Stop on 30.12: the 31.12 contract is still running, so the period that
        // would roll it into January has not been triggered yet.
        $this->simulateDailyCron($command, $membership, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-30'));

        Carbon::setTestNow();

        // January is the initial term; renewals run 31.01 -> 28.02 -> ... -> 31.12,
        // i.e. 11 monthly renewals to cover the rest of the year.
        $this->assertSame(
            '2026-12-31',
            $membership->refresh()->end_date->toDateString(),
            'Yearly monthly renewal did not land on 31.12.'
        );
        $this->assertSame(
            11,
            $membership->metadata['renewal_count'] ?? 0,
            'Expected exactly 11 renewals to reach 31.12, regardless of cancellation period.'
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
            ])
            ->onlyMethods(['createPaymentsForMembership'])
            ->getMock();

        $command->method('createPaymentsForMembership')->willReturn(0);

        return $command;
    }
}
