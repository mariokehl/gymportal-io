<?php

namespace Tests\Feature\Web;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddonCompletionTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym, 2: Member, 3: Membership, 4: Addon}
     */
    private function bookedAddonScenario(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
        ]);
        $addon = Addon::factory()->create(['gym_id' => $gym->id, 'price' => 60]);

        $membership->addons()->attach($addon->id, ['mode' => 'optional', 'price' => 60]);

        return [$owner->fresh(), $gym, $member, $membership, $addon];
    }

    #[Test]
    public function it_marks_a_booked_addon_as_completed(): void
    {
        [$owner, , $member, $membership, $addon] = $this->bookedAddonScenario();

        $this->actingAs($owner)
            ->put(route('members.memberships.addons.toggle-completion', [
                'member' => $member,
                'membership' => $membership,
                'addon' => $addon,
            ]))
            ->assertRedirect();

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertNotNull($pivot->completed_at);
        $this->assertSame($owner->id, $pivot->completed_by);
    }

    #[Test]
    public function it_resets_completion_when_toggled_again(): void
    {
        [$owner, , $member, $membership, $addon] = $this->bookedAddonScenario();

        // First toggle: mark completed.
        $this->actingAs($owner)->put(route('members.memberships.addons.toggle-completion', [
            'member' => $member,
            'membership' => $membership,
            'addon' => $addon,
        ]));

        // Second toggle: reset to open.
        $this->actingAs($owner)->put(route('members.memberships.addons.toggle-completion', [
            'member' => $member,
            'membership' => $membership,
            'addon' => $addon,
        ]));

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertNull($pivot->completed_at);
        $this->assertNull($pivot->completed_by);
    }

    #[Test]
    public function it_rejects_an_addon_not_booked_for_the_membership(): void
    {
        [$owner, $gym, $member, $membership] = $this->bookedAddonScenario();

        $unbookedAddon = Addon::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($owner)
            ->put(route('members.memberships.addons.toggle-completion', [
                'member' => $member,
                'membership' => $membership,
                'addon' => $unbookedAddon,
            ]))
            ->assertSessionHasErrors('error');
    }
}
