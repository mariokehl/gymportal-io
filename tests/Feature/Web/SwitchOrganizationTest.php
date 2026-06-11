<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A user must NEVER be able to switch their active organization (current_gym_id)
 * to a gym they do not own. Covers the HTTP boundary of GymController::switchOrganization.
 */
class SwitchOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // GymController::switchOrganization authorizes via GymPolicy::view.
        // Seed the roles referenced by the factories / policy.
        Role::factory()->create(['name' => 'Administrator', 'slug' => 'admin']);
        Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);
    }

    private function makeOwnerWithGym(): array
    {
        $owner = User::factory()->create();
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return [$owner->fresh(), $gym];
    }

    #[Test]
    public function user_cannot_switch_to_a_foreign_gym(): void
    {
        [$attacker, $ownGym] = $this->makeOwnerWithGym();
        [, $foreignGym] = $this->makeOwnerWithGym();

        $response = $this->actingAs($attacker)
            ->post(route('user.switch-organization'), ['gym_id' => $foreignGym->id]);

        $response->assertForbidden();

        // current_gym_id must remain pinned to the attacker's own gym.
        $this->assertSame(
            $ownGym->id,
            $attacker->fresh()->current_gym_id,
            'Switching to a foreign gym must not change current_gym_id.'
        );
    }

    #[Test]
    public function user_can_switch_to_a_gym_they_own(): void
    {
        $owner = User::factory()->create();
        $gymA = Gym::factory()->create(['owner_id' => $owner->id]);
        $gymB = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gymA->id]);

        $this->actingAs($owner->fresh())
            ->post(route('user.switch-organization'), ['gym_id' => $gymB->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(
            $gymB->id,
            $owner->fresh()->current_gym_id,
            'Owner must be able to switch to another gym they own.'
        );
    }

    #[Test]
    public function member_can_switch_to_a_gym_they_belong_to_via_gym_users(): void
    {
        // A trainer/staff member who does not own the gym but is linked through
        // gym_users must be able to switch into it.
        [$member, $ownGym] = $this->makeOwnerWithGym();

        $foreignOwner = User::factory()->create();
        $foreignGym = Gym::factory()->create(['owner_id' => $foreignOwner->id]);
        GymUser::create([
            'gym_id' => $foreignGym->id,
            'user_id' => $member->id,
            'role' => 'trainer',
        ]);

        $this->actingAs($member->fresh())
            ->post(route('user.switch-organization'), ['gym_id' => $foreignGym->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(
            $foreignGym->id,
            $member->fresh()->current_gym_id,
            'A gym_users member must be able to switch into the gym they belong to.'
        );
    }

    #[Test]
    public function guest_cannot_switch_organization(): void
    {
        [, $gym] = $this->makeOwnerWithGym();

        $this->post(route('user.switch-organization'), ['gym_id' => $gym->id])
            ->assertRedirect(route('login'));
    }
}
