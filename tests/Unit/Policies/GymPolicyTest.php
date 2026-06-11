<?php

namespace Tests\Unit\Policies;

use App\Models\Gym;
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

    /** Role id for "Admin". */
    private int $adminRoleId;

    /** Role id for "Gym Owner". */
    private int $ownerRoleId;

    /** Role id for a non-managing role (e.g. Trainer). */
    private int $trainerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new GymPolicy();

        // GymPolicy::manage() gates on role_id IN (1, 2) === [Admin, Owner].
        // Pin those ids explicitly so the test does not depend on seeder order.
        $this->adminRoleId = Role::factory()->create(['name' => 'Administrator', 'slug' => 'admin'])->id;
        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->trainerRoleId = Role::factory()->create(['name' => 'Trainer', 'slug' => 'trainer'])->id;
    }

    /**
     * Every ownership-gated ability, so a single regression cannot silently
     * open one of them.
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

    #[Test]
    #[DataProvider('ownershipGatedAbilities')]
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
            . 'even when it is their current_gym_id.'
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
    public function manage_requires_admin_or_owner_role_in_addition_to_ownership(): void
    {
        // Ownership alone is not enough for manage(): role must be Admin (1) or Owner (2).
        $owner = User::factory()->create(['role_id' => $this->trainerRoleId]); // Neither admin nor owner.
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $this->assertFalse(
            $this->policy->manage($owner->fresh(), $gym),
            'manage() must be denied when the role is not Admin/Owner, even for the gym owner.'
        );
    }
}
