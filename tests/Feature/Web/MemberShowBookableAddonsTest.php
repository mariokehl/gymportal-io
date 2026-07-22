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
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberShowBookableAddonsTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym, 2: Member, 3: Membership, 4: MembershipPlan}
     */
    private function membershipScenario(): array
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

        return [$owner->fresh(), $gym, $member, $membership, $plan];
    }

    #[Test]
    public function it_offers_addons_assigned_to_the_plan(): void
    {
        [$owner, $gym, $member, $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->create(['gym_id' => $gym->id, 'name' => 'Getränke-Flatrate']);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $this->actingAs($owner)
            ->get(route('members.show', $member))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Members/Show')
                ->has('bookableAddons.'.$membership->id, 1)
                ->where('bookableAddons.'.$membership->id.'.0.name', 'Getränke-Flatrate')
                ->where('bookableAddons.'.$membership->id.'.0.mode', 'optional')
            );
    }

    #[Test]
    public function it_excludes_already_booked_addons(): void
    {
        [$owner, $gym, $member, $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->create(['gym_id' => $gym->id]);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);
        $membership->addons()->attach($addon->id, ['mode' => 'optional', 'price' => 0]);

        $this->actingAs($owner)
            ->get(route('members.show', $member))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('bookableAddons.'.$membership->id, 0)
            );
    }

    #[Test]
    public function it_excludes_inactive_addons(): void
    {
        [$owner, $gym, $member, $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->inactive()->create(['gym_id' => $gym->id]);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $this->actingAs($owner)
            ->get(route('members.show', $member))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('bookableAddons.'.$membership->id, 0)
            );
    }
}
