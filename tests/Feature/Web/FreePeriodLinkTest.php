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
 * A free period is put in front of a real contract. Linking it to another
 * free period would chain trials instead of leading into a paid membership.
 */
class FreePeriodLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Role::factory()->create(['name' => 'Administrator', 'slug' => 'admin']);
        Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Member, 2: Membership, 3: Membership}
     */
    private function makeMemberWithContractAndTrial(): array
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
            'start_date' => '2026-10-14',
            'end_date' => '2026-10-31',
        ]);

        $contract = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $paidPlan->id,
            'status' => 'pending',
            'start_date' => '2026-11-01',
            'end_date' => '2027-10-31',
        ]);

        return [$owner->fresh(), $member, $contract, $freeTrial];
    }

    private function storeFreePeriod(User $owner, Member $member, int $linkedMembershipId)
    {
        return $this->actingAs($owner)->post(
            route('members.memberships.store-free-period', $member->id),
            [
                'start_date' => '2026-10-14',
                'end_date' => '2026-10-31',
                'linked_membership_id' => $linkedMembershipId,
            ],
        );
    }

    #[Test]
    public function a_free_period_cannot_be_linked_to_another_free_period(): void
    {
        [$owner, $member, , $freeTrial] = $this->makeMemberWithContractAndTrial();

        $response = $this->storeFreePeriod($owner, $member, $freeTrial->id);

        $response->assertSessionHasErrors('linked_membership_id');

        $this->assertSame(2, $member->memberships()->count());
        $this->assertNull($freeTrial->fresh()->linked_free_membership_id);
    }

    #[Test]
    public function a_free_period_can_be_linked_to_a_contract(): void
    {
        [$owner, $member, $contract] = $this->makeMemberWithContractAndTrial();

        $response = $this->storeFreePeriod($owner, $member, $contract->id);

        $response->assertSessionHasNoErrors();

        $this->assertSame(3, $member->memberships()->count());
        $this->assertNotNull($contract->fresh()->linked_free_membership_id);
    }
}
