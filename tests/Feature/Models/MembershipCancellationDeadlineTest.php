<?php

namespace Tests\Feature\Models;

use App\Models\Membership;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the cancellation_deadline accessor: the latest date on which a member
 * may cancel before the contract auto-renews for the next period.
 */
class MembershipCancellationDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time to the scenario from the ticket: today is 2026-07-04.
        Carbon::setTestNow(Carbon::parse('2026-07-04'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function it_computes_the_deadline_for_a_one_month_cancellation_period(): void
    {
        // Contract ends 2026-07-31 with a one-month cancellation period.
        // Expected: 2026-07-31 + 1 day - 1 month = 2026-07-01.
        $plan = MembershipPlan::factory()->create([
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'end_date' => '2026-07-31',
        ]);

        $this->assertSame('2026-07-01', $membership->cancellation_deadline);
    }

    #[Test]
    public function it_computes_the_deadline_for_a_day_based_cancellation_period(): void
    {
        // Contract ends 2026-07-31 with a 30-day cancellation period.
        // Expected: 2026-08-01 - 30 days = 2026-07-02.
        $plan = MembershipPlan::factory()->create([
            'cancellation_period' => 30,
            'cancellation_period_unit' => 'days',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'end_date' => '2026-07-31',
        ]);

        $this->assertSame('2026-07-02', $membership->cancellation_deadline);
    }

    #[Test]
    public function it_returns_null_without_an_end_date(): void
    {
        $plan = MembershipPlan::factory()->create();

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'end_date' => null,
        ]);

        $this->assertNull($membership->cancellation_deadline);
    }

    #[Test]
    public function it_falls_back_to_one_month_when_the_plan_has_no_cancellation_period(): void
    {
        // The DB column is NOT NULL, so a null period only surfaces on an
        // in-memory plan. The accessor must then default to one month.
        $plan = MembershipPlan::factory()->make([
            'cancellation_period' => null,
            'cancellation_period_unit' => null,
        ]);

        $membership = Membership::factory()->make([
            'end_date' => '2026-07-31',
        ]);
        $membership->setRelation('membershipPlan', $plan);

        // 2026-08-01 - 1 month = 2026-07-01.
        $this->assertSame('2026-07-01', $membership->cancellation_deadline);
    }

    #[Test]
    public function it_is_serialized_into_the_model_array(): void
    {
        $plan = MembershipPlan::factory()->create([
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'end_date' => '2026-07-31',
        ]);

        $this->assertArrayHasKey('cancellation_deadline', $membership->toArray());
        $this->assertSame('2026-07-01', $membership->toArray()['cancellation_deadline']);
    }

    #[Test]
    public function it_computes_the_deadline_for_a_monthly_rollover_after_a_one_month_initial_term(): void
    {
        // Option 1: 1-month initial term, 1-month cancellation period, monthly
        // rollover. Started 2026-03-01, so the running period ends 2026-07-31.
        // The initial term is long completed and today (04.07) is past the
        // deadline; the next renewal will run through 2026-08-31.
        $plan = MembershipPlan::factory()->create([
            'commitment_months' => 1,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-07-31',
        ]);

        $this->assertTrue($membership->isInitialTermCompleted());

        // 2026-07-31 + 1 day - 1 month = 2026-07-01, already passed on 04.07.
        $this->assertSame('2026-07-01', $membership->cancellation_deadline);
        $this->assertTrue(Carbon::now()->gte(Carbon::parse($membership->cancellation_deadline)));
    }

    #[Test]
    public function it_computes_the_deadline_during_a_twelve_month_initial_term(): void
    {
        // Option 2: 12-month initial term, 1-month cancellation period, monthly
        // rollover afterwards. Started 2026-01-01, so the initial term ends
        // 2026-12-31. On 04.07 the initial term is still running and the
        // deadline (01.12) has not been reached yet.
        $plan = MembershipPlan::factory()->create([
            'commitment_months' => 12,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        // end_date 2026-12-31 is the last day of the 12-month initial term, so the
        // commitment is considered completed on that date.
        $this->assertTrue($membership->isInitialTermCompleted());

        // 2026-12-31 + 1 day - 1 month = 2026-12-01, still in the future on 04.07.
        $this->assertSame('2026-12-01', $membership->cancellation_deadline);
        $this->assertTrue(Carbon::now()->lt(Carbon::parse($membership->cancellation_deadline)));
    }

    #[Test]
    public function initial_term_is_not_completed_before_its_last_day(): void
    {
        // A contract still mid-way through its 12-month initial term (end_date
        // before start + 12 months - 1 day) must not count as completed.
        $plan = MembershipPlan::factory()->create([
            'commitment_months' => 12,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-11-30',
        ]);

        $this->assertFalse($membership->isInitialTermCompleted());
    }

    #[Test]
    public function projected_end_date_stays_at_the_stored_end_before_the_deadline(): void
    {
        // Deadline is 01.12; on 04.07 the renewal is not due, so the projected
        // end date must still be the stored end date.
        $plan = MembershipPlan::factory()->create([
            'commitment_months' => 12,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
            'auto_renew_type' => 'monthly',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->assertSame('2026-12-31', $membership->projected_end_date);
    }

    #[Test]
    public function projected_end_date_shows_the_renewed_end_on_the_deadline_day(): void
    {
        // The UX window this attribute is built for: on the deadline day (01.07)
        // before the 02:00 cron runs, the stored end_date is still 31.07, but the
        // contract is bound to renew to 31.08. The projection must already reflect
        // that so the UI shows the correct term end.
        Carbon::setTestNow(Carbon::parse('2026-07-01 01:00:00'));

        $plan = MembershipPlan::factory()->create([
            'commitment_months' => 1,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
            'auto_renew_type' => 'monthly',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-31',
        ]);

        // Stored end_date is untouched, the projection already rolls forward.
        $this->assertSame('2026-07-31', $membership->end_date->toDateString());
        $this->assertSame('2026-08-31', $membership->projected_end_date);

        Carbon::setTestNow();
    }

    #[Test]
    public function projected_end_date_is_null_for_indefinite_rollover_on_the_deadline_day(): void
    {
        // Same due-renewal situation, but the plan rolls over to an indefinite
        // contract: the cron would clear end_date, so the projection is null.
        Carbon::setTestNow(Carbon::parse('2026-07-01 01:00:00'));

        $plan = MembershipPlan::factory()->create([
            'commitment_months' => 1,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
            'auto_renew_type' => 'indefinite',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-31',
        ]);

        $this->assertNull($membership->projected_end_date);

        Carbon::setTestNow();
    }

    #[Test]
    public function projected_end_date_is_null_without_an_end_date(): void
    {
        $plan = MembershipPlan::factory()->create();

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'end_date' => null,
        ]);

        $this->assertNull($membership->projected_end_date);
    }

    #[Test]
    public function projected_end_date_rolls_forward_monthly_even_within_the_initial_term(): void
    {
        // Renewal is always monthly, never by another full commitment length —
        // even when end_date still sits inside the initial term. Term end 30.11,
        // deadline 01.11; on 01.11 the projection must be 31.12, not a year ahead.
        Carbon::setTestNow(Carbon::parse('2026-11-01'));

        $plan = MembershipPlan::factory()->create([
            'commitment_months' => 12,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
            'auto_renew_type' => 'monthly',
        ]);

        $membership = Membership::factory()->create([
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-11-30',
        ]);

        $this->assertFalse($membership->isInitialTermCompleted());
        $this->assertSame('2026-12-31', $membership->projected_end_date);

        Carbon::setTestNow();
    }
}
