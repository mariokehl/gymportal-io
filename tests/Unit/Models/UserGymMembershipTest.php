<?php

namespace Tests\Unit\Models;

use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the per-organization membership helpers on the User model that drive
 * the organization switcher and the gym authorization policy: roleInGym(),
 * canManageGym() and accessibleGyms().
 */
class UserGymMembershipTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    #[Test]
    public function owner_has_owner_role_in_their_own_gym(): void
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame('owner', $owner->roleInGym($gym));
        $this->assertTrue($owner->canManageGym($gym));
        $this->assertTrue($owner->belongsToGym($gym));
    }

    #[Test]
    public function pivot_role_applies_when_user_is_not_the_owner(): void
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);

        $trainer = User::factory()->create(['role_id' => $this->ownerRoleId]);
        GymUser::create(['gym_id' => $gym->id, 'user_id' => $trainer->id, 'role' => 'trainer']);

        $this->assertSame('trainer', $trainer->fresh()->roleInGym($gym));
        $this->assertFalse($trainer->fresh()->canManageGym($gym));
        $this->assertTrue($trainer->fresh()->belongsToGym($gym));
    }

    #[Test]
    public function admin_pivot_role_can_manage(): void
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);

        $admin = User::factory()->create(['role_id' => $this->ownerRoleId]);
        GymUser::create(['gym_id' => $gym->id, 'user_id' => $admin->id, 'role' => 'admin']);

        $this->assertTrue($admin->fresh()->canManageGym($gym));
    }

    #[Test]
    public function role_is_null_for_a_gym_without_relationship(): void
    {
        $stranger = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $foreignGym = Gym::factory()->create();

        $this->assertNull($stranger->roleInGym($foreignGym));
        $this->assertFalse($stranger->canManageGym($foreignGym));
        $this->assertFalse($stranger->belongsToGym($foreignGym));
    }

    #[Test]
    public function accessible_gyms_unions_owned_and_member_gyms_without_duplicates(): void
    {
        $user = User::factory()->create(['role_id' => $this->ownerRoleId]);

        // Owned gym.
        $owned = Gym::factory()->create(['owner_id' => $user->id]);

        // Membership in a foreign gym.
        $foreign = Gym::factory()->create();
        GymUser::create(['gym_id' => $foreign->id, 'user_id' => $user->id, 'role' => 'trainer']);

        // A gym the user both owns AND has a pivot row in must not be duplicated.
        $both = Gym::factory()->create(['owner_id' => $user->id]);
        GymUser::create(['gym_id' => $both->id, 'user_id' => $user->id, 'role' => 'admin']);

        $accessible = $user->fresh()->accessibleGyms();

        $this->assertCount(3, $accessible);
        $this->assertEqualsCanonicalizing(
            [$owned->id, $foreign->id, $both->id],
            $accessible->pluck('id')->all()
        );
    }
}
