<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\GymUser;
use App\Models\Member;
use App\Models\MembershipPlan;
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

    /*
    | Redirect target — used by the "Standort wechseln" dialog in the live log,
    | which switches and then opens the visiting member's profile or contract.
    */

    #[Test]
    public function it_lands_on_the_member_after_switching(): void
    {
        $owner = User::factory()->create();
        $berlin = Gym::factory()->create(['owner_id' => $owner->id]);
        $hamburg = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $berlin->id]);

        $member = Member::factory()->create(['gym_id' => $hamburg->id]);

        $this->actingAs($owner->fresh())
            ->post(route('user.switch-organization'), [
                'gym_id' => $hamburg->id,
                'target' => 'member',
                'target_id' => $member->id,
            ])
            ->assertRedirect(route('members.show', $member->id));

        $this->assertSame($hamburg->id, $owner->fresh()->current_gym_id);
    }

    #[Test]
    public function it_lands_on_the_contract_after_switching(): void
    {
        $owner = User::factory()->create();
        $berlin = Gym::factory()->create(['owner_id' => $owner->id]);
        $hamburg = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $berlin->id]);

        $plan = MembershipPlan::factory()->create(['gym_id' => $hamburg->id]);

        $this->actingAs($owner->fresh())
            ->post(route('user.switch-organization'), [
                'gym_id' => $hamburg->id,
                'target' => 'contract',
                'target_id' => $plan->id,
            ])
            ->assertRedirect(route('contracts.edit', $plan->id));
    }

    #[Test]
    public function a_target_outside_the_gym_falls_back_to_the_dashboard(): void
    {
        $owner = User::factory()->create();
        $berlin = Gym::factory()->create(['owner_id' => $owner->id]);
        $hamburg = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $berlin->id]);

        // A member of a gym the user is NOT switching into. Redirecting there
        // would only produce a 403, so the dashboard is the honest landing.
        $strangerGym = Gym::factory()->create();
        $strangerMember = Member::factory()->create(['gym_id' => $strangerGym->id]);

        $this->actingAs($owner->fresh())
            ->post(route('user.switch-organization'), [
                'gym_id' => $hamburg->id,
                'target' => 'member',
                'target_id' => $strangerMember->id,
            ])
            ->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function an_unknown_target_is_rejected(): void
    {
        [$owner, $gym] = $this->makeOwnerWithGym();

        // The target is an allowlist, so it can never become an open redirect.
        $this->actingAs($owner)
            ->post(route('user.switch-organization'), [
                'gym_id' => $gym->id,
                'target' => 'https://evil.example.com',
                'target_id' => 1,
            ])
            ->assertSessionHasErrors('target');
    }

    #[Test]
    public function a_switch_without_a_target_still_lands_on_the_dashboard(): void
    {
        $owner = User::factory()->create();
        $gymA = Gym::factory()->create(['owner_id' => $owner->id]);
        $gymB = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gymA->id]);

        $this->actingAs($owner->fresh())
            ->post(route('user.switch-organization'), [
                'gym_id' => $gymB->id,
                'target' => null,
                'target_id' => null,
            ])
            ->assertRedirect(route('dashboard'));
    }

    /*
    | Return path — the sidebar switcher keeps the current page (and tab) when
    | the user is allowed to open it in the target organization.
    */

    /**
     * @return array{0: User, 1: Gym}
     */
    private function makeOwnerWithTwoGyms(): array
    {
        $owner = User::factory()->create();
        $berlin = Gym::factory()->create(['owner_id' => $owner->id]);
        $munich = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $berlin->id]);

        return [$owner->fresh(), $munich];
    }

    private function switchWithReturnPath(User $user, Gym $gym, string $returnTo)
    {
        return $this->actingAs($user)
            ->post(route('user.switch-organization'), [
                'gym_id' => $gym->id,
                'return_to' => $returnTo,
            ]);
    }

    #[Test]
    public function it_keeps_the_settings_tab_after_switching(): void
    {
        [$owner, $munich] = $this->makeOwnerWithTwoGyms();

        $this->switchWithReturnPath($owner, $munich, '/settings?tab=emails')
            ->assertRedirect(route('settings.index', ['tab' => 'emails']));

        $this->assertSame($munich->id, $owner->fresh()->current_gym_id);
    }

    #[Test]
    public function staff_without_management_rights_lands_on_the_dashboard(): void
    {
        [$staff] = $this->makeOwnerWithGym();

        $munich = Gym::factory()->create();
        GymUser::create(['gym_id' => $munich->id, 'user_id' => $staff->id, 'role' => 'staff']);

        // Settings are reserved for owners/admins of the target gym.
        $this->switchWithReturnPath($staff->fresh(), $munich, '/settings?tab=emails')
            ->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function staff_keeps_a_page_they_may_open_in_the_target_gym(): void
    {
        [$staff] = $this->makeOwnerWithGym();

        $munich = Gym::factory()->create();
        GymUser::create(['gym_id' => $munich->id, 'user_id' => $staff->id, 'role' => 'staff']);

        $this->switchWithReturnPath($staff->fresh(), $munich, '/members')
            ->assertRedirect(route('members.index'));
    }

    #[Test]
    public function a_record_page_falls_back_to_its_list(): void
    {
        [$owner, $munich] = $this->makeOwnerWithTwoGyms();

        // The member belongs to the previous gym — never link to it after the switch.
        $member = Member::factory()->create(['gym_id' => $owner->current_gym_id]);

        $this->switchWithReturnPath($owner, $munich, "/members/{$member->id}?tab=payments")
            ->assertRedirect(route('members.index'));
    }

    #[Test]
    public function only_the_tab_parameter_is_carried_over(): void
    {
        [$owner, $munich] = $this->makeOwnerWithTwoGyms();

        $this->switchWithReturnPath($owner, $munich, '/members?page=4&saved_filter=12')
            ->assertRedirect(route('members.index'));

        $this->switchWithReturnPath($owner, $munich, '/settings?tab=%3Cscript%3E')
            ->assertRedirect(route('settings.index'));
    }

    #[Test]
    public function a_foreign_return_path_is_never_followed(): void
    {
        [$owner, $munich] = $this->makeOwnerWithTwoGyms();

        foreach (['https://evil.example.com/settings', '//evil.example.com/settings', '/does-not-exist', '/user/switch-organization'] as $returnTo) {
            $this->switchWithReturnPath($owner, $munich, $returnTo)
                ->assertRedirect(route('dashboard'));
        }
    }
}
