<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberCreatePlanListTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym}
     */
    private function ownerWithGym(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return [$owner->fresh(), $gym];
    }

    #[Test]
    public function it_excludes_free_trial_plans_from_the_selectable_plans(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $regular = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'name' => 'Premium']);
        $trial = MembershipPlan::factory()->freeTrial()->create(['gym_id' => $gym->id, 'name' => 'Gratis-Testzeitraum']);

        $response = $this->actingAs($owner)->get(route('members.create'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($regular, $trial) {
            $ids = collect($page->toArray()['props']['membershipPlans'])->pluck('id');

            $this->assertContains($regular->id, $ids);
            $this->assertNotContains($trial->id, $ids);
        });
    }

    #[Test]
    public function it_only_lists_plans_of_the_current_gym(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        [, $otherGym] = $this->ownerWithGym();

        $own = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $foreign = MembershipPlan::factory()->create(['gym_id' => $otherGym->id]);

        $response = $this->actingAs($owner)->get(route('members.create'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($own, $foreign) {
            $ids = collect($page->toArray()['props']['membershipPlans'])->pluck('id');

            $this->assertContains($own->id, $ids);
            $this->assertNotContains($foreign->id, $ids);
        });
    }

    #[Test]
    public function it_excludes_free_trial_plans_from_the_add_membership_list(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $regular = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'name' => 'Premium']);
        $trial = MembershipPlan::factory()->freeTrial()->create(['gym_id' => $gym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $response = $this->actingAs($owner)->get(route('members.show', $member));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($regular, $trial) {
            $ids = collect($page->toArray()['props']['membershipPlans'])->pluck('id');

            $this->assertContains($regular->id, $ids);
            $this->assertNotContains($trial->id, $ids);
        });
    }

    #[Test]
    public function it_rejects_a_free_trial_plan_when_adding_a_membership(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $trial = MembershipPlan::factory()->freeTrial()->create(['gym_id' => $gym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $response = $this->actingAs($owner)->post(route('members.memberships.store', $member), [
            'membership_plan_id' => $trial->id,
            'start_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('membership_plan_id');
    }

    #[Test]
    public function it_rejects_a_plan_of_another_gym_when_adding_a_membership(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        [, $otherGym] = $this->ownerWithGym();

        $foreign = MembershipPlan::factory()->create(['gym_id' => $otherGym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $response = $this->actingAs($owner)->post(route('members.memberships.store', $member), [
            'membership_plan_id' => $foreign->id,
            'start_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('membership_plan_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(int $planId): array
    {
        return [
            'salutation' => 'Herr',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max.mustermann@example.test',
            'status' => 'pending',
            'joined_date' => now()->toDateString(),
            'payment_method' => 'invoice',
            'membership_plan_id' => $planId,
        ];
    }

    #[Test]
    public function it_rejects_a_free_trial_plan_when_creating_a_member(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $trial = MembershipPlan::factory()->freeTrial()->create(['gym_id' => $gym->id]);

        $response = $this->actingAs($owner)
            ->post(route('members.store'), $this->memberPayload($trial->id));

        $response->assertSessionHasErrors('membership_plan_id');
        $this->assertDatabaseCount('members', 0);
    }

    #[Test]
    public function it_rejects_a_plan_of_another_gym_when_creating_a_member(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        [, $otherGym] = $this->ownerWithGym();

        $foreign = MembershipPlan::factory()->create(['gym_id' => $otherGym->id]);

        $response = $this->actingAs($owner)
            ->post(route('members.store'), $this->memberPayload($foreign->id));

        $response->assertSessionHasErrors('membership_plan_id');
        $this->assertDatabaseCount('members', 0);
    }

    #[Test]
    public function it_still_accepts_a_regular_plan_of_the_own_gym(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $gym->updateStandardPaymentMethod('invoice', true);

        $regular = MembershipPlan::factory()->create(['gym_id' => $gym->id]);

        $response = $this->actingAs($owner)
            ->post(route('members.store'), $this->memberPayload($regular->id));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('members', [
            'gym_id' => $gym->id,
            'email' => 'max.mustermann@example.test',
        ]);
    }
}
