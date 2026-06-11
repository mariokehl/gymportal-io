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
 * Inline editing of a team member's name is reserved for the gym owner. Admins,
 * staff and trainers must not be able to rename team members, and the change
 * updates the linked user account.
 */
class UpdateGymUserNameTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym, 2: GymUser}
     */
    private function gymWithMember(string $memberRole = 'trainer'): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $member = User::factory()->create(['first_name' => 'Old', 'last_name' => 'Name']);
        $gymUser = GymUser::create(['gym_id' => $gym->id, 'user_id' => $member->id, 'role' => $memberRole]);

        return [$owner->fresh(), $gym, $gymUser];
    }

    #[Test]
    public function owner_can_rename_a_team_member(): void
    {
        [$owner, , $gymUser] = $this->gymWithMember();

        $this->actingAs($owner)
            ->putJson(route('settings.gym-users.update-name', $gymUser->id), [
                'first_name' => 'New',
                'last_name' => 'Person',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $gymUser->user_id,
            'first_name' => 'New',
            'last_name' => 'Person',
        ]);
    }

    #[Test]
    public function admin_member_cannot_rename_a_team_member(): void
    {
        [, $gym, $gymUser] = $this->gymWithMember();

        // An admin (manages the gym) is still NOT the owner.
        $admin = User::factory()->create();
        GymUser::create(['gym_id' => $gym->id, 'user_id' => $admin->id, 'role' => 'admin']);
        $admin->update(['current_gym_id' => $gym->id]);

        $this->actingAs($admin->fresh())
            ->putJson(route('settings.gym-users.update-name', $gymUser->id), [
                'first_name' => 'Hacked',
                'last_name' => 'Name',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $gymUser->user_id,
            'first_name' => 'Old',
            'last_name' => 'Name',
        ]);
    }

    #[Test]
    public function staff_member_cannot_rename_a_team_member(): void
    {
        [, $gym, $gymUser] = $this->gymWithMember();

        $staff = User::factory()->create();
        GymUser::create(['gym_id' => $gym->id, 'user_id' => $staff->id, 'role' => 'staff']);
        $staff->update(['current_gym_id' => $gym->id]);

        $this->actingAs($staff->fresh())
            ->putJson(route('settings.gym-users.update-name', $gymUser->id), [
                'first_name' => 'Nope',
                'last_name' => 'Nope',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function owner_of_another_gym_cannot_rename(): void
    {
        [, , $gymUser] = $this->gymWithMember();

        $strangerOwner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $otherGym = Gym::factory()->create(['owner_id' => $strangerOwner->id]);
        $strangerOwner->update(['current_gym_id' => $otherGym->id]);

        $this->actingAs($strangerOwner->fresh())
            ->putJson(route('settings.gym-users.update-name', $gymUser->id), [
                'first_name' => 'Stranger',
                'last_name' => 'Edit',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function name_fields_are_required(): void
    {
        [$owner, , $gymUser] = $this->gymWithMember();

        $this->actingAs($owner)
            ->putJson(route('settings.gym-users.update-name', $gymUser->id), [
                'first_name' => '',
                'last_name' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    #[Test]
    public function guest_cannot_rename(): void
    {
        [, , $gymUser] = $this->gymWithMember();

        $this->putJson(route('settings.gym-users.update-name', $gymUser->id), [
            'first_name' => 'X',
            'last_name' => 'Y',
        ])->assertUnauthorized();
    }
}
