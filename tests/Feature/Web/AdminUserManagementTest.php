<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private const BASIC_USER = 'admin-test';

    private const BASIC_PASSWORD = 'admin-test-password';

    private User $admin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.basic.username' => self::BASIC_USER,
            'auth.basic.password' => self::BASIC_PASSWORD,
        ]);

        $adminRole = Role::factory()->create(['id' => UserPolicy::ADMIN_ROLE_ID, 'name' => 'Admin', 'slug' => 'admin']);
        $ownerRole = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->owner = User::factory()->create(['role_id' => $ownerRole->id]);
    }

    private function asAdmin(?User $user = null): static
    {
        return $this->actingAs($user ?? $this->admin)
            ->withBasicAuth(self::BASIC_USER, self::BASIC_PASSWORD);
    }

    #[Test]
    public function it_blocks_a_user_with_a_reason(): void
    {
        $this->asAdmin()
            ->post(route('admin.users.block', $this->owner), ['reason' => 'Zahlungsrückstand'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->owner->refresh();
        $this->assertTrue($this->owner->is_blocked);
        $this->assertSame('Zahlungsrückstand', $this->owner->blocked_reason);
    }

    #[Test]
    public function it_unblocks_a_user(): void
    {
        $this->owner->update(['is_blocked' => true, 'blocked_reason' => 'Test']);

        $this->asAdmin()
            ->delete(route('admin.users.unblock', $this->owner))
            ->assertSessionHas('success');

        $this->owner->refresh();
        $this->assertFalse($this->owner->is_blocked);
        $this->assertNull($this->owner->blocked_reason);
    }

    #[Test]
    public function it_deletes_the_user_after_removing_all_owned_gyms(): void
    {
        $berlin = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $hamburg = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $member = Member::factory()->create(['gym_id' => $berlin->id]);
        $this->owner->update(['current_gym_id' => $berlin->id]);

        $foreignGym = Gym::factory()->create();
        $foreignGym->users()->attach($this->owner->id, ['role' => 'staff']);

        $this->asAdmin()
            ->delete(route('admin.users.destroy', $this->owner), ['confirmation' => $this->owner->email])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $this->owner->id]);
        $this->assertSoftDeleted('gyms', ['id' => $berlin->id]);
        $this->assertSoftDeleted('gyms', ['id' => $hamburg->id]);
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertSame(0, DB::table('gym_users')->where('user_id', $this->owner->id)->count());

        // Gyms of other owners stay untouched
        $this->assertNotSoftDeleted('gyms', ['id' => $foreignGym->id]);
    }

    #[Test]
    public function it_requires_the_email_as_confirmation_for_the_deletion(): void
    {
        $gym = Gym::factory()->create(['owner_id' => $this->owner->id]);

        $this->asAdmin()
            ->delete(route('admin.users.destroy', $this->owner), ['confirmation' => 'wrong@example.com'])
            ->assertSessionHasErrors('confirmation');

        $this->assertNotSoftDeleted('users', ['id' => $this->owner->id]);
        $this->assertNotSoftDeleted('gyms', ['id' => $gym->id]);
    }

    #[Test]
    public function it_forbids_non_admins_to_block_or_delete_users(): void
    {
        $other = User::factory()->create(['role_id' => $this->owner->role_id]);

        $this->asAdmin($other)
            ->post(route('admin.users.block', $this->owner))
            ->assertForbidden();

        $this->asAdmin($other)
            ->delete(route('admin.users.destroy', $this->owner), ['confirmation' => $this->owner->email])
            ->assertForbidden();

        $this->assertFalse($this->owner->fresh()->is_blocked);
        $this->assertNotSoftDeleted('users', ['id' => $this->owner->id]);
    }

    #[Test]
    public function it_forbids_blocking_or_deleting_admins(): void
    {
        $otherAdmin = User::factory()->create(['role_id' => UserPolicy::ADMIN_ROLE_ID]);

        $this->asAdmin()
            ->post(route('admin.users.block', $otherAdmin))
            ->assertForbidden();

        $this->asAdmin()
            ->delete(route('admin.users.destroy', $this->admin), ['confirmation' => $this->admin->email])
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
    }

    #[Test]
    public function it_requires_basic_auth(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.block', $this->owner))
            ->assertUnauthorized();

        $this->assertFalse($this->owner->fresh()->is_blocked);
    }

    #[Test]
    public function it_lists_the_verification_and_the_paid_subscriptions_of_the_users(): void
    {
        $this->owner->update(['email_verified_at' => now()]);
        $unverified = User::factory()->unverified()->create(['role_id' => $this->owner->role_id]);

        Gym::factory()->create([
            'owner_id' => $this->owner->id,
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ]);
        Gym::factory()->create([
            'owner_id' => $this->owner->id,
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->subMonth(),
            'trial_ends_at' => now()->subMonths(2),
        ]);
        Gym::factory()->create([
            'owner_id' => $this->owner->id,
            'subscription_status' => 'cancelled',
            'trial_ends_at' => now()->subDay(),
        ]);
        Gym::factory()->create([
            'owner_id' => $this->owner->id,
            'subscription_status' => 'inactive',
            'subscription_ends_at' => null,
            'trial_ends_at' => now()->addWeek(),
        ]);

        // A paid gym that is still within its trial only counts as paid
        Gym::factory()->create([
            'owner_id' => $this->owner->id,
            'trial_ends_at' => now()->addWeek(),
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ]);

        $response = $this->asAdmin()->get(route('impersonate.index'))->assertOk();

        $users = collect($response->viewData('page')['props']['users'])->keyBy('id');

        $this->assertNotNull($users[$this->owner->id]['email_verified_at']);
        $this->assertSame(5, $users[$this->owner->id]['owned_gyms_count']);
        $this->assertSame(2, $users[$this->owner->id]['paid_gyms_count']);
        $this->assertSame(1, $users[$this->owner->id]['trial_gyms_count']);
        $this->assertSame(2, $users[$this->owner->id]['expired_gyms_count']);

        $this->assertNull($users[$unverified->id]['email_verified_at']);
        $this->assertSame(0, $users[$unverified->id]['paid_gyms_count']);
    }

    #[Test]
    public function it_lists_paying_users_first_then_trials_then_the_rest(): void
    {
        $roleId = $this->owner->role_id;
        $noGym = User::factory()->create(['role_id' => $roleId]);
        $paidTwiceOlder = User::factory()->create(['role_id' => $roleId]);
        $expired = User::factory()->create(['role_id' => $roleId]);
        $trialOlder = User::factory()->create(['role_id' => $roleId]);
        $paid = User::factory()->create(['role_id' => $roleId]);
        $trialNewer = User::factory()->create(['role_id' => $roleId]);
        $paidTwice = User::factory()->create(['role_id' => $roleId]);

        Gym::factory()->withoutActiveSubscription()->create(['owner_id' => $expired->id]);
        Gym::factory()->create(['owner_id' => $paid->id]);
        Gym::factory()->count(2)->create(['owner_id' => $paidTwice->id]);
        Gym::factory()->count(2)->create(['owner_id' => $paidTwiceOlder->id]);
        foreach ([$trialOlder, $trialNewer] as $user) {
            Gym::factory()->create([
                'owner_id' => $user->id,
                'subscription_status' => 'inactive',
                'subscription_ends_at' => null,
                'trial_ends_at' => now()->addWeek(),
            ]);
        }

        $response = $this->asAdmin()->get(route('impersonate.index'))->assertOk();

        $ids = collect($response->viewData('page')['props']['users'])->pluck('id')->all();

        // More paid subscriptions rank higher; on a tie the newest user comes first
        $this->assertSame(
            [$paidTwice->id, $paidTwiceOlder->id, $paid->id, $trialNewer->id, $trialOlder->id, $expired->id, $noGym->id, $this->owner->id],
            $ids
        );
    }

    #[Test]
    public function it_forbids_impersonating_a_blocked_user(): void
    {
        $this->owner->update(['is_blocked' => true]);

        $this->asAdmin()
            ->post(route('impersonate.start', $this->owner))
            ->assertSessionHas('error');

        $this->assertAuthenticatedAs($this->admin);
    }
}
