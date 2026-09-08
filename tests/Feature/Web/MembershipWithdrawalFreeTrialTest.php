<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A paid contract that starts on the first of the month is preceded by a linked
 * free trial period. Withdrawing the paid contract must end that trial as well,
 * otherwise the member keeps access through a membership nobody pays for.
 */
class MembershipWithdrawalFreeTrialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Role::factory()->create(['name' => 'Administrator', 'slug' => 'admin']);
        Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);

        Carbon::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Member, 2: Membership, 3: Membership}
     */
    private function makeWithdrawableContract(): array
    {
        $owner = User::factory()->create();
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $trialPlan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'is_free_trial_plan' => true,
        ]);

        $paidPlan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'is_free_trial_plan' => false,
        ]);

        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $freeTrial = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $trialPlan->id,
            'status' => 'active',
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-30',
        ]);

        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $paidPlan->id,
            'status' => 'active',
            'start_date' => '2026-10-01',
            'end_date' => '2027-09-30',
            'linked_free_membership_id' => $freeTrial->id,
        ]);

        return [$owner->fresh(), $member, $membership, $freeTrial];
    }

    private function withdraw(User $owner, Member $member, Membership $membership)
    {
        return $this->actingAs($owner)->put(
            route('members.memberships.withdraw', [
                'member' => $member->id,
                'membership' => $membership->id,
            ]),
            [],
        );
    }

    #[Test]
    public function withdrawing_a_contract_expires_the_linked_free_trial(): void
    {
        [$owner, $member, $membership, $freeTrial] = $this->makeWithdrawableContract();

        $response = $this->withdraw($owner, $member, $membership);

        $response->assertSessionHasNoErrors();

        $this->assertSame('withdrawn', $membership->fresh()->status);

        $freeTrial->refresh();

        $this->assertSame('expired', $freeTrial->status);
        $this->assertSame('2026-09-08', $freeTrial->end_date->format('Y-m-d'));
        $this->assertStringContainsString(
            'Gratis-Testzeitraum beendet durch Widerruf',
            (string) $freeTrial->notes,
        );
    }

    #[Test]
    public function a_trial_that_already_ended_keeps_its_end_date(): void
    {
        [$owner, $member, $membership, $freeTrial] = $this->makeWithdrawableContract();

        // The trial ran out before the withdrawal
        $freeTrial->update(['end_date' => '2026-09-01']);

        $this->withdraw($owner, $member, $membership)->assertSessionHasNoErrors();

        $freeTrial->refresh();

        $this->assertSame('expired', $freeTrial->status);

        // The withdrawal must not push the end date forward
        $this->assertSame('2026-09-01', $freeTrial->end_date->format('Y-m-d'));
    }

    #[Test]
    public function withdrawing_a_contract_without_a_linked_free_trial_still_works(): void
    {
        [$owner, $member, $membership] = $this->makeWithdrawableContract();

        $membership->update(['linked_free_membership_id' => null]);

        $response = $this->withdraw($owner, $member, $membership);

        $response->assertSessionHasNoErrors();
        $this->assertSame('withdrawn', $membership->fresh()->status);
    }
}
