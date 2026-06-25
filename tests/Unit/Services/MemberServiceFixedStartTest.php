<?php

namespace Tests\Unit\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Services\MemberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberServiceFixedStartTest extends TestCase
{
    use RefreshDatabase;

    private MemberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MemberService::class);
    }

    public function test_fixed_start_date_is_used_when_reference_is_before_it(): void
    {
        Carbon::setTestNow('2026-08-15');

        $gym = Gym::factory()->create(['contracts_start_first_of_month' => true]);
        $member = Member::factory()->create(['gym_id' => $gym->id, 'joined_date' => Carbon::now()]);
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'start_date_mode' => 'fixed',
            'fixed_start_date' => '2026-10-01',
            'commitment_months' => 0,
        ]);

        $result = $this->service->createMembershipWithFreePeriod($member, $plan, Carbon::now());

        // start_date must equal the fixed date and no free period membership is created
        $this->assertSame('2026-10-01', $result['membership']->start_date->format('Y-m-d'));
        $this->assertNull($result['free_membership']);
    }

    public function test_fixed_start_date_no_longer_applies_once_reached(): void
    {
        // From the fixed date onwards the normal next-possible logic applies again.
        // With contracts_start_first_of_month disabled, start_date = the given start date.
        Carbon::setTestNow('2026-10-05');

        $gym = Gym::factory()->create(['contracts_start_first_of_month' => false]);
        $member = Member::factory()->create(['gym_id' => $gym->id, 'joined_date' => Carbon::now()]);
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'start_date_mode' => 'fixed',
            'fixed_start_date' => '2026-10-01',
            'commitment_months' => 0,
        ]);

        $result = $this->service->createMembershipWithFreePeriod($member, $plan, Carbon::now());

        $this->assertSame('2026-10-05', $result['membership']->start_date->format('Y-m-d'));
        $this->assertNull($result['free_membership']);
    }

    public function test_next_possible_mode_keeps_free_period_logic(): void
    {
        // Default mode: mid-month registration with first-of-month setting => free period precedes paid membership.
        Carbon::setTestNow('2026-08-15');

        $gym = Gym::factory()->create(['contracts_start_first_of_month' => true]);
        $member = Member::factory()->create(['gym_id' => $gym->id, 'joined_date' => Carbon::now()]);
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'start_date_mode' => 'next_possible',
            'commitment_months' => 0,
        ]);

        $result = $this->service->createMembershipWithFreePeriod($member, $plan, Carbon::now());

        $this->assertNotNull($result['free_membership']);
        $this->assertSame('2026-09-01', $result['membership']->start_date->format('Y-m-d'));
    }

    public function test_resolve_forced_start_date_helper(): void
    {
        $plan = MembershipPlan::factory()->make([
            'start_date_mode' => 'fixed',
            'fixed_start_date' => '2026-10-01',
        ]);

        $this->assertSame('2026-10-01', $plan->resolveForcedStartDate(Carbon::parse('2026-09-30'))->format('Y-m-d'));
        $this->assertNull($plan->resolveForcedStartDate(Carbon::parse('2026-10-01')));
        $this->assertNull($plan->resolveForcedStartDate(Carbon::parse('2026-10-02')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
