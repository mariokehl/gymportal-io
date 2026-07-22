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

class MembershipAddonBookingTest extends TestCase
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

    private function book(User $user, Member $member, Membership $membership, int $addonId)
    {
        return $this->actingAs($user)
            ->post(route('members.memberships.addons.store', [
                'member' => $member,
                'membership' => $membership,
            ]), ['addon_id' => $addonId]);
    }

    #[Test]
    public function it_books_an_optional_addon_assigned_to_the_plan(): void
    {
        [$owner, $gym, $member, $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->create(['gym_id' => $gym->id, 'price' => 60]);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $this->book($owner, $member, $membership, $addon->id)->assertRedirect();

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertSame('optional', $pivot->mode);
        $this->assertEquals(60.0, (float) $pivot->price);
    }

    #[Test]
    public function it_books_an_included_addon_at_zero_price(): void
    {
        [$owner, $gym, $member, $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->create(['gym_id' => $gym->id, 'price' => 60]);
        $plan->addons()->attach($addon->id, ['mode' => 'included']);

        $this->book($owner, $member, $membership, $addon->id)->assertRedirect();

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertSame('included', $pivot->mode);
        $this->assertEquals(0.0, (float) $pivot->price);
    }

    #[Test]
    public function booking_does_not_create_a_payment(): void
    {
        [$owner, $gym, $member, $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->create(['gym_id' => $gym->id, 'price' => 60]);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $this->book($owner, $member, $membership, $addon->id);

        $this->assertSame(0, $member->payments()->count());
        $this->assertNull($membership->addons()->find($addon->id)->pivot->payment_id);
    }

    #[Test]
    public function it_rejects_an_addon_not_assigned_to_the_plan(): void
    {
        [$owner, $gym, $member, $membership] = $this->membershipScenario();

        // Belongs to the gym, but is not assigned to this membership's plan.
        $addon = Addon::factory()->create(['gym_id' => $gym->id]);

        $this->book($owner, $member, $membership, $addon->id)
            ->assertSessionHasErrors('error');

        $this->assertSame(0, $membership->addons()->count());
    }

    #[Test]
    public function it_rejects_an_inactive_addon(): void
    {
        [$owner, $gym, $member, $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->inactive()->create(['gym_id' => $gym->id]);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $this->book($owner, $member, $membership, $addon->id)
            ->assertSessionHasErrors('error');

        $this->assertSame(0, $membership->addons()->count());
    }

    #[Test]
    public function it_rejects_booking_the_same_addon_twice(): void
    {
        [$owner, $gym, $member, $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->create(['gym_id' => $gym->id]);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $this->book($owner, $member, $membership, $addon->id);
        $this->book($owner, $member, $membership, $addon->id)
            ->assertSessionHasErrors('error');

        $this->assertSame(1, $membership->addons()->count());
    }

    #[Test]
    public function it_forbids_booking_for_a_membership_of_another_member(): void
    {
        [$owner, $gym, , $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->create(['gym_id' => $gym->id]);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $otherMember = Member::factory()->create(['gym_id' => $gym->id]);

        $this->book($owner, $otherMember, $membership, $addon->id)->assertForbidden();
    }

    #[Test]
    public function it_forbids_booking_for_a_membership_of_another_gym(): void
    {
        [, $gym, $member, $membership, $plan] = $this->membershipScenario();

        $addon = Addon::factory()->create(['gym_id' => $gym->id]);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $stranger = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $strangerGym = Gym::factory()->create(['owner_id' => $stranger->id]);
        $stranger->update(['current_gym_id' => $strangerGym->id]);

        $this->book($stranger->fresh(), $member, $membership, $addon->id)->assertForbidden();
    }

    #[Test]
    public function it_requires_an_existing_addon_id(): void
    {
        [$owner, , $member, $membership] = $this->membershipScenario();

        $this->actingAs($owner)
            ->post(route('members.memberships.addons.store', [
                'member' => $member,
                'membership' => $membership,
            ]), [])
            ->assertSessionHasErrors('addon_id');
    }
}
