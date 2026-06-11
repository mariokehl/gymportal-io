<?php

namespace Tests\Feature\Web;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Inertia shared props must expose every organization a user can access —
 * gyms they own AND gyms they belong to via gym_users — each carrying the
 * per-organization role and management flag. This is what lets a team member
 * switch between organizations A, B, … while keeping management functions
 * restricted to the organizations where they are owner or admin.
 */
class SharedOrganizationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);
    }

    /**
     * Resolve the 'auth.user' shared prop for the given user exactly as Inertia
     * would on a real request.
     *
     * @return array<string, mixed>
     */
    private function sharedUser(User $user): array
    {
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $shared = (new HandleInertiaRequests())->share($request);
        $resolved = value($shared['auth']['user']);

        return $resolved;
    }

    #[Test]
    public function all_gyms_contains_owned_and_member_gyms_with_per_org_flags(): void
    {
        $user = User::factory()->create();

        // Organization A: the user is the owner.
        $gymA = Gym::factory()->create(['owner_id' => $user->id, 'name' => 'Org A']);

        // Organization B: the user is a trainer (member, not owner).
        $ownerB = User::factory()->create();
        $gymB = Gym::factory()->create(['owner_id' => $ownerB->id, 'name' => 'Org B']);
        GymUser::create(['gym_id' => $gymB->id, 'user_id' => $user->id, 'role' => 'trainer']);

        $user->update(['current_gym_id' => $gymA->id]);

        $shared = $this->sharedUser($user->fresh());
        $allGyms = collect($shared['all_gyms'])->keyBy('id');

        $this->assertCount(2, $allGyms, 'Both the owned and the member gym must be listed.');

        $this->assertSame('owner', $allGyms[$gymA->id]['role']);
        $this->assertTrue($allGyms[$gymA->id]['can_manage'], 'Owner may manage organization A.');

        $this->assertSame('trainer', $allGyms[$gymB->id]['role']);
        $this->assertFalse($allGyms[$gymB->id]['can_manage'], 'Trainer may not manage organization B.');
    }

    #[Test]
    public function current_gym_reflects_the_role_in_the_active_organization(): void
    {
        $user = User::factory()->create();

        $ownerB = User::factory()->create();
        $gymB = Gym::factory()->create(['owner_id' => $ownerB->id]);
        GymUser::create(['gym_id' => $gymB->id, 'user_id' => $user->id, 'role' => 'trainer']);

        // Active organization is one where the user is only a trainer.
        $user->update(['current_gym_id' => $gymB->id]);

        $shared = $this->sharedUser($user->fresh());

        $this->assertSame($gymB->id, $shared['current_gym']['id']);
        $this->assertSame('trainer', $shared['current_gym']['role']);
        $this->assertFalse(
            $shared['current_gym']['can_manage'],
            'A trainer must not be offered management functions in the active organization.'
        );
    }
}
