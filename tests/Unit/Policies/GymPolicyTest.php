<?php

namespace Tests\Unit\Policies;

use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Role;
use App\Models\User;
use App\Policies\GymPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards the authorization boundary between gyms: a user must NEVER be able to
 * view, update, delete or manage a gym they do not own — regardless of which
 * gym is currently selected (current_gym_id).
 *
 * Regression cover for the bug where GymPolicy::update/delete checked
 * current_gym_id instead of ownership, which both denied legitimate owners and
 * risked authorizing the wrong gym.
 */
class GymPolicyTest extends TestCase
{
    use RefreshDatabase;

    private GymPolicy $policy;

    /** Role id for "Gym Owner". */
    private int $ownerRoleId;

    /** Role id for a non-managing global role (e.g. Trainer). */
    private int $trainerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new GymPolicy;

        // Management is now gated on the per-organization role, not the global
        // users.role_id; the global role only proves it is irrelevant here.
        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->trainerRoleId = Role::factory()->create(['name' => 'Trainer', 'slug' => 'trainer'])->id;
    }

    /**
     * Every ability that an owner must be allowed on their own gym.
     *
     * @return array<string, array{0: string}>
     */
    public static function ownerAbilities(): array
    {
        return [
            'view' => ['view'],
            'update' => ['update'],
            'delete' => ['delete'],
            'manage' => ['manage'],
        ];
    }

    /**
     * Abilities that a complete stranger (no relationship to the gym) must never
     * be granted.
     *
     * @return array<string, array{0: string}>
     */
    public static function ownershipGatedAbilities(): array
    {
        return [
            'view' => ['view'],
            'update' => ['update'],
            'delete' => ['delete'],
            'manage' => ['manage'],
        ];
    }

    /**
     * Management abilities that must be denied to staff/trainer members even
     * though they may view (switch into) the gym.
     *
     * @return array<string, array{0: string}>
     */
    public static function managementAbilities(): array
    {
        return [
            'update' => ['update'],
            'delete' => ['delete'],
            'manage' => ['manage'],
        ];
    }

    #[Test]
    #[DataProvider('ownerAbilities')]
    public function owner_is_allowed_on_their_own_gym(string $ability): void
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        // Owner has this gym selected as well.
        $owner->update(['current_gym_id' => $gym->id]);

        $this->assertTrue(
            $this->policy->{$ability}($owner->fresh(), $gym),
            "Owner must be allowed to {$ability} their own gym."
        );
    }

    #[Test]
    #[DataProvider('ownershipGatedAbilities')]
    public function stranger_is_denied_on_a_foreign_gym(string $ability): void
    {
        $stranger = User::factory()->create(['role_id' => $this->ownerRoleId]); // Even with Owner role...
        $foreignGym = Gym::factory()->create(); // ...owned by somebody else.

        // Worst case: the stranger has the foreign gym selected as current_gym_id.
        // The old policy would have WRONGLY allowed update/delete/manage here.
        $stranger->update(['current_gym_id' => $foreignGym->id]);

        $this->assertFalse(
            $this->policy->{$ability}($stranger->fresh(), $foreignGym),
            "A non-owner must NEVER be allowed to {$ability} a foreign gym, "
            .'even when it is their current_gym_id.'
        );
    }

    #[Test]
    public function owner_of_one_gym_is_denied_on_another_owners_gym(): void
    {
        $ownerA = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gymA = Gym::factory()->create(['owner_id' => $ownerA->id]);
        $ownerA->update(['current_gym_id' => $gymA->id]);

        $ownerB = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gymB = Gym::factory()->create(['owner_id' => $ownerB->id]);

        foreach (['view', 'update', 'delete', 'manage'] as $ability) {
            $this->assertFalse(
                $this->policy->{$ability}($ownerA->fresh(), $gymB),
                "Owner A must NEVER {$ability} gym B owned by owner B."
            );
        }
    }

    #[Test]
    public function owner_can_update_and_delete_a_non_active_gym_they_own(): void
    {
        // The original bug: owner of multiple gyms, with a DIFFERENT gym active,
        // was denied on a gym they actually own.
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $activeGym = Gym::factory()->create(['owner_id' => $owner->id]);
        $otherOwnedGym = Gym::factory()->create(['owner_id' => $owner->id]);

        $owner->update(['current_gym_id' => $activeGym->id]);
        $owner = $owner->fresh();

        $this->assertTrue(
            $this->policy->update($owner, $otherOwnedGym),
            'Owner must be able to update a gym they own even when it is not the active gym.'
        );
        $this->assertTrue(
            $this->policy->delete($owner, $otherOwnedGym),
            'Owner must be able to delete a gym they own even when it is not the active gym.'
        );
    }

    #[Test]
    public function owner_may_manage_regardless_of_their_global_role(): void
    {
        // The per-organization model treats the gym owner as 'owner' within their
        // own gym, independent of the global users.role_id.
        $owner = User::factory()->create(['role_id' => $this->trainerRoleId]); // Global role is irrelevant.
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $this->assertTrue(
            $this->policy->manage($owner->fresh(), $gym),
            'The gym owner must always be able to manage their own gym.'
        );
    }

    #[Test]
    public function admin_member_may_view_and_manage_a_gym_they_do_not_own(): void
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);

        $admin = User::factory()->create(['role_id' => $this->trainerRoleId]);
        GymUser::create(['gym_id' => $gym->id, 'user_id' => $admin->id, 'role' => 'admin']);
        $admin->update(['current_gym_id' => $gym->id]);

        $admin = $admin->fresh();
        $this->assertTrue($this->policy->view($admin, $gym), 'Admin member must be able to view the gym.');
        $this->assertTrue($this->policy->manage($admin, $gym), 'Admin member must be able to manage the gym.');
        $this->assertTrue($this->policy->update($admin, $gym), 'Admin member must be able to update the gym.');
    }

    #[Test]
    #[DataProvider('managementAbilities')]
    public function staff_and_trainer_members_may_view_but_never_manage(string $ability): void
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);

        foreach (['staff', 'trainer'] as $role) {
            $member = User::factory()->create(['role_id' => $this->trainerRoleId]);
            GymUser::create(['gym_id' => $gym->id, 'user_id' => $member->id, 'role' => $role]);
            $member->update(['current_gym_id' => $gym->id]);
            $member = $member->fresh();

            $this->assertTrue(
                $this->policy->view($member, $gym),
                "A {$role} member must be able to view (switch into) the gym."
            );
            $this->assertFalse(
                $this->policy->{$ability}($member, $gym),
                "A {$role} member must NEVER be allowed to {$ability} the gym."
            );
        }
    }

    #[Test]
    public function non_member_cannot_view_a_foreign_gym(): void
    {
        // Regression: a user with no relationship at all (not even gym_users)
        // must not be able to view/switch into a gym.
        $stranger = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $foreignGym = Gym::factory()->create();
        $stranger->update(['current_gym_id' => $foreignGym->id]);

        $this->assertFalse(
            $this->policy->view($stranger->fresh(), $foreignGym),
            'A non-member must never be able to view a foreign gym.'
        );
    }
}
