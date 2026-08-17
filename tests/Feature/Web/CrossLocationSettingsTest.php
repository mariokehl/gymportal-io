<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the two screens that configure cross-location check-in: the location
 * rule under Zugangskontrolle / Konfiguration and the contract's Standorte tab.
 */
class CrossLocationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $berlin;

    private Gym $hamburg;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;

        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->berlin = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Berlin']);
        $this->hamburg = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Hamburg']);

        $this->owner->update(['current_gym_id' => $this->berlin->id]);
        $this->owner = $this->owner->fresh();
    }

    /*
    | Location rule
    */

    #[Test]
    public function it_stores_the_selected_locations(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('access-control.cross-location.update'), [
                'rule' => Gym::CHECKIN_RULE_SELECTED,
                'allowed_gym_ids' => [$this->hamburg->id],
            ])
            ->assertOk();

        $this->berlin->refresh();

        $this->assertSame(Gym::CHECKIN_RULE_SELECTED, $this->berlin->cross_location_checkin_rule);
        $this->assertSame([$this->hamburg->id], $this->berlin->allowedCheckinGyms()->pluck('gyms.id')->all());
    }

    #[Test]
    public function it_rejects_locations_of_another_organisation(): void
    {
        // Its own owner, so not part of this organisation.
        $outsideGym = Gym::factory()->create();

        $this->actingAs($this->owner)
            ->putJson(route('access-control.cross-location.update'), [
                'rule' => Gym::CHECKIN_RULE_SELECTED,
                'allowed_gym_ids' => [$this->hamburg->id, $outsideGym->id],
            ])
            ->assertOk();

        // The foreign id is dropped rather than stored — it would otherwise open
        // this location's door to an unrelated studio.
        $this->assertSame(
            [$this->hamburg->id],
            $this->berlin->allowedCheckinGyms()->pluck('gyms.id')->all()
        );
    }

    #[Test]
    public function it_refuses_a_selected_rule_without_any_location(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('access-control.cross-location.update'), [
                'rule' => Gym::CHECKIN_RULE_SELECTED,
                'allowed_gym_ids' => [],
            ])
            ->assertStatus(422);

        $this->assertSame(Gym::CHECKIN_RULE_OWN, $this->berlin->fresh()->cross_location_checkin_rule);
    }

    #[Test]
    public function switching_away_from_selected_clears_the_stored_locations(): void
    {
        $this->berlin->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_SELECTED]);
        $this->berlin->allowedCheckinGyms()->sync([$this->hamburg->id]);

        $this->actingAs($this->owner)
            ->putJson(route('access-control.cross-location.update'), [
                'rule' => Gym::CHECKIN_RULE_OWN,
            ])
            ->assertOk();

        $this->assertSame([], $this->berlin->allowedCheckinGyms()->pluck('gyms.id')->all());
    }

    #[Test]
    public function it_rejects_an_unknown_rule(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('access-control.cross-location.update'), ['rule' => 'everyone'])
            ->assertStatus(422);
    }

    #[Test]
    public function a_member_without_manage_rights_cannot_change_the_rule(): void
    {
        // Belongs to this organisation, but is not allowed to manage the gym.
        $staff = User::factory()->create(['current_gym_id' => $this->berlin->id]);

        $this->actingAs($staff->fresh())
            ->putJson(route('access-control.cross-location.update'), [
                'rule' => Gym::CHECKIN_RULE_ALL,
            ])
            ->assertForbidden();

        $this->assertSame(Gym::CHECKIN_RULE_OWN, $this->berlin->fresh()->cross_location_checkin_rule);
    }

    #[Test]
    public function a_contract_of_another_organisation_cannot_be_changed(): void
    {
        $stranger = User::factory()->create();
        $strangerGym = Gym::factory()->create(['owner_id' => $stranger->id]);
        $foreignPlan = MembershipPlan::factory()->create(['gym_id' => $strangerGym->id]);

        $this->actingAs($this->owner)
            ->putJson(route('contracts.locations.update', $foreignPlan->id), [
                'location_scope' => MembershipPlan::SCOPE_ALL,
            ])
            ->assertForbidden();

        $this->assertSame(MembershipPlan::SCOPE_OWN, $foreignPlan->fresh()->location_scope);
    }

    /*
    | Contract scope
    */

    #[Test]
    public function it_stores_the_contract_location_scope(): void
    {
        $plan = MembershipPlan::factory()->create(['gym_id' => $this->berlin->id]);

        $this->actingAs($this->owner)
            ->putJson(route('contracts.locations.update', $plan->id), [
                'location_scope' => MembershipPlan::SCOPE_SELECTED,
                'allowed_gym_ids' => [$this->hamburg->id],
            ])
            ->assertOk();

        $plan->refresh();

        $this->assertSame(MembershipPlan::SCOPE_SELECTED, $plan->location_scope);
        $this->assertSame([$this->hamburg->id], $plan->allowedGyms()->pluck('gyms.id')->all());
    }

    #[Test]
    public function the_contract_scope_ignores_locations_outside_the_organisation(): void
    {
        $plan = MembershipPlan::factory()->create(['gym_id' => $this->berlin->id]);
        $outsideGym = Gym::factory()->create();

        $this->actingAs($this->owner)
            ->putJson(route('contracts.locations.update', $plan->id), [
                'location_scope' => MembershipPlan::SCOPE_SELECTED,
                'allowed_gym_ids' => [$this->hamburg->id, $outsideGym->id],
            ])
            ->assertOk();

        $this->assertSame([$this->hamburg->id], $plan->allowedGyms()->pluck('gyms.id')->all());
    }

    #[Test]
    public function the_contract_scope_refuses_selected_without_a_location(): void
    {
        $plan = MembershipPlan::factory()->create(['gym_id' => $this->berlin->id]);

        $this->actingAs($this->owner)
            ->putJson(route('contracts.locations.update', $plan->id), [
                'location_scope' => MembershipPlan::SCOPE_SELECTED,
                'allowed_gym_ids' => [],
            ])
            ->assertStatus(422);

        $this->assertSame(MembershipPlan::SCOPE_OWN, $plan->fresh()->location_scope);
    }

    #[Test]
    public function the_edit_screen_exposes_the_effect_preview(): void
    {
        $this->berlin->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_ALL]);
        $this->hamburg->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_ALL]);

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->berlin->id,
            'location_scope' => MembershipPlan::SCOPE_ALL,
        ]);

        $this->actingAs($this->owner)
            ->get(route('contracts.edit', $plan->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MembershipPlans/Edit')
                ->where('locationScope.scope', MembershipPlan::SCOPE_ALL)
                ->where('locationScope.has_siblings', true)
                ->has('locationScope.effect', 2)
            );
    }
}
