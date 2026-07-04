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

        $this->assertFalse($membership->isInitialTermCompleted());

        // 2026-12-31 + 1 day - 1 month = 2026-12-01, still in the future on 04.07.
        $this->assertSame('2026-12-01', $membership->cancellation_deadline);
        $this->assertTrue(Carbon::now()->lt(Carbon::parse($membership->cancellation_deadline)));
    }
}
