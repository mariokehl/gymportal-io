<?php

namespace Tests\Feature\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\MonthlyInsightsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The counting rules of MonthlyInsightsService one by one: withdrawn,
 * pending and soft-deleted contracts, and members at the turn of the month.
 * The endpoint with abilities and the shape of the answer is covered in
 * AgentMonthlyInsightsTest.
 */
class MonthlyInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private MembershipPlan $flex;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-11-26 12:00:00');

        $this->gym = Gym::factory()->create();
        $this->flex = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id, 'name' => 'Flex', 'price' => 40.00, 'billing_cycle' => 'monthly',
            'is_free_trial_plan' => false, 'commitment_months' => 1, 'cancellation_period' => 1, 'cancellation_period_unit' => 'months',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function contract(string $start, array $attributes = [], ?Member $member = null): Membership
    {
        $member ??= Member::factory()->create(['gym_id' => $this->gym->id, 'status' => 'active', 'joined_date' => $start]);

        return Membership::factory()->create([
            'member_id' => $member->id, 'membership_plan_id' => $this->flex->id, 'start_date' => $start, 'end_date' => null,
            'status' => 'active', 'cancellation_date' => null, 'cancellation_received_at' => null, ...$attributes,
        ]);
    }

    /**
     * @return array{0: Collection<string, array>, 1: array}
     */
    private function insights(): array
    {
        $data = app(MonthlyInsightsService::class)->forGym($this->gym, 3, today(), true);

        return [collect($data['months'])->keyBy('month'), $data];
    }

    private function column(Collection $byMonth, string $key): array
    {
        return $byMonth->pluck($key)->values()->all();
    }

    #[Test]
    public function a_withdrawn_contract_ends_on_the_day_of_the_withdrawal(): void
    {
        // MembershipService::withdraw sets only status and withdrawn_at, no cancellation or end date
        $this->contract('2026-08-01', ['status' => 'withdrawn', 'withdrawn_at' => '2026-10-10 10:00:00']);

        [$byMonth, $data] = $this->insights();

        $this->assertSame([1, 0, 0], $this->column($byMonth, 'members_at_end'));
        $this->assertSame([0, 1, 0], $this->column($byMonth, 'leavers'));
        $this->assertEquals([40.0, 0.0, 0.0], $this->column($byMonth, 'list_fees'));
        $this->assertEquals(40.0, $byMonth['2026-10']['list_fees_left']);
        $this->assertSame([], $data['contracts_not_ended_by_plan']);
    }

    #[Test]
    public function a_contract_withdrawn_before_it_began_never_ran(): void
    {
        // A presale contract withdrawn while still pending: no join, no member, no leaver, no fee
        $this->contract('2026-10-01', ['status' => 'withdrawn', 'withdrawn_at' => '2026-09-20 18:00:00']);

        [$byMonth, $data] = $this->insights();

        foreach (['joins', 'leavers', 'members_at_end'] as $key) {
            $this->assertSame([0, 0, 0], $this->column($byMonth, $key), $key);
        }
        $this->assertEquals([0.0, 0.0, 0.0], $this->column($byMonth, 'list_fees_left'));
        $this->assertSame([], $data['contracts_not_ended_by_plan']);
        $this->assertSame([], $data['pending_contracts_by_plan']);
    }

    #[Test]
    public function a_pending_contract_counts_only_once_it_is_active(): void
    {
        $signUp = $this->contract('2026-10-15', ['status' => 'pending']);          // widget sign-up, not yet activated
        $this->contract('2026-09-01', ['status' => 'expired', 'end_date' => '2026-10-01',
            'cancellation_reason' => Membership::PENDING_TIMEOUT_REASON]);          // ran into the pending timeout, never active

        [$byMonth, $data] = $this->insights();

        foreach (['joins', 'leavers', 'members_at_end'] as $key) {
            $this->assertSame([0, 0, 0], $this->column($byMonth, $key), $key);
        }
        $this->assertEquals([0.0, 0.0, 0.0], $this->column($byMonth, 'list_fees'));
        $this->assertSame(['Flex' => 1], $data['pending_contracts_by_plan']);
        $this->assertSame([], $data['contracts_not_ended_by_plan']);

        $signUp->update(['status' => 'active']);
        [$byMonth, $data] = $this->insights();

        $this->assertSame([0, 1, 0], $this->column($byMonth, 'joins'));
        $this->assertSame([0, 1, 1], $this->column($byMonth, 'members_at_end'));
        $this->assertSame(['Flex' => 1], $data['contracts_not_ended_by_plan']);
        $this->assertSame([], $data['pending_contracts_by_plan']);
    }

    #[Test]
    public function soft_deleted_records_do_not_rewrite_past_months(): void
    {
        $leaver = $this->contract('2026-08-10', ['status' => 'expired', 'cancellation_date' => '2026-10-31',
            'cancellation_received_at' => '2026-09-15 09:00:00']);
        $this->contract('2026-09-05');

        [$before] = $this->insights();

        $leaver->delete();
        $leaver->member->delete();
        $this->assertSoftDeleted($leaver);
        $this->assertSoftDeleted($leaver->member);
        [$afterDeletingMember] = $this->insights();

        $this->flex->delete();
        $this->assertSoftDeleted($this->flex);
        [$afterDeletingPlan] = $this->insights();

        $this->assertSame([0, 1, 0], $this->column($before, 'leavers'));             // the leaver leaves in October
        $this->assertSame([1, 0, 0], $this->column($before, 'cancellations_received'));
        $this->assertSame($before->all(), $afterDeletingMember->all());
        $this->assertSame($before->all(), $afterDeletingPlan->all());
    }

    #[Test]
    public function members_at_month_end_follow_the_turn_of_the_month(): void
    {
        $this->contract('2026-10-01');                                              // joins on the first: a join of October only
        $this->contract('2026-07-01', ['status' => 'expired', 'cancellation_date' => '2026-09-30']);   // leaves on the last day
        $switch = $this->contract('2026-06-01', ['status' => 'expired', 'end_date' => '2026-09-30']);  // changes plan, stays one member
        $this->contract('2026-10-01', [], $switch->member);

        [$byMonth] = $this->insights();

        $this->assertSame([1, 2, 2], $this->column($byMonth, 'members_at_end'));
        $this->assertSame([0, 1, 0], $this->column($byMonth, 'joins'));
        $this->assertSame([1, 0, 0], $this->column($byMonth, 'leavers'));
        $this->assertSame(1, $byMonth['2026-10']['members_at_start']);
        $this->assertEquals([40.0, 80.0, 80.0], $this->column($byMonth, 'list_fees'));
    }
}
