<?php

namespace Tests\Feature\Web;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipPlanAddonAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;

        $this->owner = User::factory()->create(['role_id' => $ownerRoleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $this->owner->update(['current_gym_id' => $this->gym->id]);
    }

    /**
     * Valid plan payload; addon fields are merged in per test.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Basic',
            'description' => 'Test plan',
            'price' => '49.95',
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'commitment_months' => 12,
            'cancellation_period' => 30,
            'cancellation_period_unit' => 'days',
            'auto_renew_type' => 'indefinite',
            'start_date_mode' => 'next_possible',
        ], $overrides);
    }

    private function plan(array $attributes = []): MembershipPlan
    {
        return MembershipPlan::factory()->create(array_merge([
            'gym_id' => $this->gym->id,
            'price' => 49.95,
        ], $attributes));
    }

    private function addon(array $attributes = []): Addon
    {
        return Addon::factory()->create(array_merge([
            'gym_id' => $this->gym->id,
        ], $attributes));
    }

    #[Test]
    public function it_assigns_addons_when_creating_a_plan(): void
    {
        $included = $this->addon(['name' => 'Getränke-Flat']);
        $optional = $this->addon(['name' => 'Sauna']);

        $response = $this->actingAs($this->owner)->post(route('contracts.store'), $this->planPayload([
            'addon_modes' => [
                $included->id => 'included',
                $optional->id => 'optional',
            ],
        ]));

        $response->assertRedirect(route('contracts.index'));

        $plan = MembershipPlan::where('gym_id', $this->gym->id)->firstOrFail();

        $this->assertSame('included', $plan->addons()->find($included->id)->pivot->mode);
        $this->assertSame('optional', $plan->addons()->find($optional->id)->pivot->mode);
    }

    #[Test]
    public function it_replaces_the_assignments_when_updating_a_plan(): void
    {
        $plan = $this->plan();
        $kept = $this->addon();
        $dropped = $this->addon();

        $plan->addons()->attach([
            $kept->id => ['mode' => 'optional'],
            $dropped->id => ['mode' => 'included'],
        ]);

        $this->actingAs($this->owner)
            ->put(route('contracts.update', $plan), $this->planPayload([
                'addon_modes' => [$kept->id => 'included'],
            ]))
            ->assertRedirect(route('contracts.index'));

        $this->assertSame('included', $plan->addons()->find($kept->id)->pivot->mode);
        $this->assertNull($plan->addons()->find($dropped->id));
    }

    #[Test]
    public function it_detaches_every_addon_when_the_map_is_empty(): void
    {
        $plan = $this->plan();
        $addon = $this->addon();
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $this->actingAs($this->owner)
            ->put(route('contracts.update', $plan), $this->planPayload())
            ->assertRedirect(route('contracts.index'));

        $this->assertSame(0, $plan->addons()->count());
    }

    #[Test]
    public function it_rejects_an_unknown_assignment_mode(): void
    {
        $addon = $this->addon();

        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload([
                'addon_modes' => [$addon->id => 'free'],
            ]))
            ->assertSessionHasErrors("addon_modes.{$addon->id}");
    }

    #[Test]
    public function it_ignores_addons_of_another_gym(): void
    {
        $foreignGym = Gym::factory()->create();
        $foreignAddon = Addon::factory()->create(['gym_id' => $foreignGym->id]);

        $plan = $this->plan();

        $this->actingAs($this->owner)
            ->put(route('contracts.update', $plan), $this->planPayload([
                'addon_modes' => [$foreignAddon->id => 'included'],
            ]))
            ->assertRedirect(route('contracts.index'));

        $this->assertSame(0, $plan->addons()->count());
    }

    #[Test]
    public function it_never_assigns_addons_to_a_free_trial_plan(): void
    {
        $plan = $this->plan(['is_free_trial_plan' => true]);
        $addon = $this->addon();

        $this->actingAs($this->owner)
            ->put(route('contracts.update', $plan), $this->planPayload([
                'addon_modes' => [$addon->id => 'included'],
            ]))
            ->assertRedirect(route('contracts.index'));

        $this->assertSame(0, $plan->addons()->count());
    }

    #[Test]
    public function it_clears_addons_left_on_a_free_trial_plan(): void
    {
        $plan = $this->plan(['is_free_trial_plan' => true]);
        $addon = $this->addon();
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $this->actingAs($this->owner)
            ->put(route('contracts.update', $plan), $this->planPayload())
            ->assertRedirect(route('contracts.index'));

        $this->assertSame(0, $plan->addons()->count());
    }

    #[Test]
    public function the_edit_page_only_offers_addons_of_the_current_gym(): void
    {
        $ownAddon = $this->addon(['name' => 'Sauna']);
        $foreignAddon = Addon::factory()->create(['gym_id' => Gym::factory()->create()->id]);

        $plan = $this->plan();
        $plan->addons()->attach($ownAddon->id, ['mode' => 'included']);

        $this->actingAs($this->owner)
            ->get(route('contracts.edit', $plan))
            ->assertInertia(fn ($page) => $page
                ->component('MembershipPlans/Edit')
                ->has('addons', 1)
                ->where('addons.0.id', $ownAddon->id)
                ->where("addonModes.{$ownAddon->id}", 'included')
            );

        $this->assertNotNull($foreignAddon);
    }
}
