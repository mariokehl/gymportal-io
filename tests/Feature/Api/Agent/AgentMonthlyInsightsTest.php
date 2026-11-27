<?php

namespace Tests\Feature\Api\Agent;

use App\Models\Gym;
use App\Models\GymApiClient;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * insights/monthly gives a dashboard its counts per month without a single
 * person in the answer: joins, cancellations, leavers, members, churn rate,
 * and list fees only for a token that may read payments. The counting rules
 * one by one are covered in MonthlyInsightsServiceTest.
 */
class AgentMonthlyInsightsTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private MembershipPlan $flex;

    private MembershipPlan $year;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-11-26 12:00:00');

        $this->gym = Gym::factory()->create();
        $this->flex = $this->plan('Flex', 40.00, 'monthly');
        $this->year = $this->plan('Jahr', 360.00, 'yearly');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plan(string $name, float $price, string $cycle, bool $trial = false, ?Gym $gym = null): MembershipPlan
    {
        return MembershipPlan::factory()->create([
            'gym_id' => ($gym ?? $this->gym)->id, 'name' => $name, 'price' => $price, 'billing_cycle' => $cycle,
            'is_free_trial_plan' => $trial, 'commitment_months' => 1, 'cancellation_period' => 1, 'cancellation_period_unit' => 'months',
        ]);
    }

    private function contract(MembershipPlan $plan, string $start, string $status = 'active', ?string $cancelled = null, ?string $received = null, ?string $end = null, ?Member $member = null): Member
    {
        $member ??= Member::factory()->create(['gym_id' => $plan->gym_id, 'status' => 'active', 'joined_date' => $start]);
        Membership::factory()->create([
            'member_id' => $member->id, 'membership_plan_id' => $plan->id, 'start_date' => $start, 'end_date' => $end, 'status' => $status,
            'cancellation_date' => $cancelled, 'cancellation_received_at' => $received,
        ]);

        return $member;
    }

    private function insights(array $abilities, string $query = '?months=3')
    {
        $token = GymApiClient::issue($this->gym, 'Cockpit', 'Tests', $abilities)[1];

        return $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->getJson('/api/v1/agent/insights/monthly'.$query);
    }

    #[Test]
    public function it_counts_joins_cancellations_leavers_members_and_fees_per_month(): void
    {
        $this->contract($this->flex, '2026-06-10');                                                   // stays
        $this->contract($this->year, '2026-09-05');                                                   // joins in September, 30 a month
        $this->contract($this->flex, '2026-10-02', 'expired', '2026-10-31', '2026-10-15 09:00:00');   // joins and leaves in October
        $this->contract($this->flex, '2026-07-01', 'active', '2026-12-31', '2026-11-03 10:00:00');    // cancels in November, still a member
        $switch = $this->contract($this->flex, '2026-05-01', 'expired', null, null, '2026-10-31');     // changes plan, no leave
        $this->contract($this->year, '2026-11-01', 'active', null, null, null, $switch);
        $trial = $this->plan('Probe', 0, 'monthly', true);
        $this->contract($trial, '2026-11-20', 'expired', null, null, '2026-11-27');                    // free trial does not count
        $this->contract($this->flex, '2027-03-01', 'pending');                                         // presale, starts later
        $this->contract($this->flex, '2027-03-01', 'pending', '2026-11-20', '2026-11-19 08:00:00');   // presale cancelled before the start: a cancellation, no leaver

        $other = Gym::factory()->create();
        $this->contract($this->plan('Fremd', 50, 'monthly', false, $other), '2026-11-01');            // other gym, never counted

        $data = $this->insights(['stats.read', 'payments.read'])->assertOk()->json('data');
        $byMonth = collect($data['months'])->keyBy('month');

        $this->assertSame(['2026-09', '2026-10', '2026-11'], $byMonth->keys()->all());
        $this->assertSame(['cancellations_received', 'churn_rate_percent', 'joins', 'leavers', 'list_fees', 'list_fees_joined', 'list_fees_left',
            'members_at_end', 'members_at_start', 'month'], collect($byMonth['2026-10'])->keys()->sort()->values()->all());
        $this->assertSame([1, 1, 0], $byMonth->pluck('joins')->values()->all());
        $this->assertSame([0, 1, 2], $byMonth->pluck('cancellations_received')->values()->all());
        $this->assertSame([0, 1, 0], $byMonth->pluck('leavers')->values()->all());
        $this->assertSame([4, 4, 4], $byMonth->pluck('members_at_end')->values()->all());
        $this->assertEquals(25.0, $byMonth['2026-10']['churn_rate_percent']);
        $this->assertSame(4, $byMonth['2026-10']['members_at_start']);
        // list fees at month end: September 40 + 30 + 40 + 40, then the plan change to 30 a month
        $this->assertEquals([150.0, 140.0, 140.0], $byMonth->pluck('list_fees')->values()->all());
        $this->assertEquals(30.0, $byMonth['2026-11']['list_fees_joined']);
        $this->assertEquals(80.0, $byMonth['2026-10']['list_fees_left']);    // the leaver and the old plan of the switcher
        $this->assertSame(['Flex' => 2, 'Jahr' => 2], $data['contracts_not_ended_by_plan']);
        $this->assertSame(['Flex' => 1], $data['pending_contracts_by_plan']);    // the presale contract that was not cancelled
        $this->assertStringContainsString('not revenue', $data['list_fees_basis']);
        $this->assertStringNotContainsString('@', json_encode($data));
    }

    #[Test]
    public function without_payments_read_there_are_no_list_fees(): void
    {
        $this->contract($this->flex, '2026-06-10');

        $data = $this->insights(['stats.read'])->assertOk()->json('data');

        $this->assertSame('needs ability payments.read', $data['money_omitted']);
        $this->assertArrayNotHasKey('list_fees_basis', $data);
        foreach ($data['months'] as $row) {
            $this->assertSame([], array_values(array_filter(array_keys($row), fn ($key) => str_contains($key, 'fee'))));
            $this->assertArrayNotHasKey('list_fees', $row);
            $this->assertArrayNotHasKey('list_fees_joined', $row);
            $this->assertArrayNotHasKey('list_fees_left', $row);
        }
    }

    #[Test]
    public function it_needs_stats_read_and_caps_the_months(): void
    {
        $this->insights(['members.read'])->assertStatus(403);
        $this->assertCount(36, $this->insights(['stats.read'], '?months=99')->assertOk()->json('data.months'));
    }
}
