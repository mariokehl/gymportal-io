<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Operators can hide a finished membership from the member's history. The row
 * is only soft deleted, so payments keep their reference, and running
 * contracts must never be removable this way.
 */
class MembershipIgnoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::factory()->create(['name' => 'Administrator', 'slug' => 'admin']);
        Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);
    }

    /**
     * @return array{0: User, 1: Member, 2: Membership}
     */
    private function makeMembership(array $attributes = []): array
    {
        $owner = User::factory()->create();
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $membership = Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'expired',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ], $attributes));

        return [$owner->fresh(), $member, $membership];
    }

    private function ignore(User $owner, Member $member, Membership $membership)
    {
        return $this->actingAs($owner)->delete(
            route('members.memberships.destroy', [
                'member' => $member->id,
                'membership' => $membership->id,
            ])
        );
    }

    #[Test]
    public function it_soft_deletes_a_finished_membership(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $this->ignore($owner, $member, $membership)->assertSessionHas('success');

        $this->assertSoftDeleted('memberships', ['id' => $membership->id]);
    }

    #[Test]
    public function it_keeps_the_membership_out_of_the_member_page(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $this->ignore($owner, $member, $membership);

        $this->assertFalse(
            $member->fresh()->memberships()->where('id', $membership->id)->exists()
        );
    }

    #[Test]
    public function it_refuses_to_hide_a_running_membership(): void
    {
        [$owner, $member, $membership] = $this->makeMembership(['status' => 'active']);

        $this->ignore($owner, $member, $membership)->assertSessionHasErrors('error');

        $this->assertNotSoftDeleted('memberships', ['id' => $membership->id]);
    }

    #[Test]
    public function it_hides_a_linked_free_period_along_with_its_contract(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $freePeriod = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $membership->membership_plan_id,
            'status' => 'expired',
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
        ]);

        $membership->update(['linked_free_membership_id' => $freePeriod->id]);

        $this->ignore($owner, $member, $membership->fresh());

        $this->assertSoftDeleted('memberships', ['id' => $membership->id]);
        $this->assertSoftDeleted('memberships', ['id' => $freePeriod->id]);
    }

    #[Test]
    public function it_keeps_a_still_running_linked_membership(): void
    {
        [$owner, $member, $freePeriod] = $this->makeMembership();

        $running = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $freePeriod->membership_plan_id,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'linked_free_membership_id' => $freePeriod->id,
        ]);

        $this->ignore($owner, $member, $freePeriod);

        $this->assertSoftDeleted('memberships', ['id' => $freePeriod->id]);
        $this->assertNotSoftDeleted('memberships', ['id' => $running->id]);
    }

    #[Test]
    public function it_denies_access_across_gym_boundaries(): void
    {
        [, $member, $membership] = $this->makeMembership();

        $otherOwner = User::factory()->create();
        $otherGym = Gym::factory()->create(['owner_id' => $otherOwner->id]);
        $otherOwner->update(['current_gym_id' => $otherGym->id]);

        $this->ignore($otherOwner->fresh(), $member, $membership)->assertForbidden();

        $this->assertNotSoftDeleted('memberships', ['id' => $membership->id]);
    }
}
