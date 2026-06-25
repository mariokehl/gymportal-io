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

class AddonControllerTest extends TestCase
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
    public function it_creates_an_addon_with_plan_assignments(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $includedPlan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $optionalPlan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), [
                'name' => 'Trainereinweisung',
                'description' => 'Einmalige Einweisung durch einen Trainer',
                'price' => 29.90,
                'payment_method' => 'cash',
                'is_active' => true,
                'plan_modes' => [
                    $includedPlan->id => 'included',
                    $optionalPlan->id => 'optional',
                ],
            ])
            ->assertRedirect(route('contracts.addons.index'));

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertSame('Trainereinweisung', $addon->name);
        $this->assertSame('cash', $addon->payment_method);
        $this->assertSame('included', $addon->membershipPlans()->find($includedPlan->id)->pivot->mode);
        $this->assertSame('optional', $addon->membershipPlans()->find($optionalPlan->id)->pivot->mode);
    }

    #[Test]
    public function it_only_assigns_plans_belonging_to_the_current_gym(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $foreignGym = Gym::factory()->create();
        $foreignPlan = MembershipPlan::factory()->create(['gym_id' => $foreignGym->id]);

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), [
                'name' => 'Foreign test',
                'price' => 10,
                'plan_modes' => [$foreignPlan->id => 'included'],
            ]);

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertCount(0, $addon->membershipPlans);
    }

    #[Test]
    public function it_updates_an_addon_and_syncs_assignments(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $addon = Addon::factory()->create(['gym_id' => $gym->id]);
        $addon->membershipPlans()->attach($plan->id, ['mode' => 'optional']);

        $this->actingAs($owner)
            ->put(route('contracts.addons.update', $addon), [
                'name' => 'Updated name',
                'price' => 49.00,
                'payment_method' => '',
                'is_active' => false,
                'plan_modes' => [$plan->id => 'included'],
            ])
            ->assertRedirect(route('contracts.addons.index'));

        $addon->refresh();

        $this->assertSame('Updated name', $addon->name);
        $this->assertNull($addon->payment_method);
        $this->assertFalse($addon->is_active);
        $this->assertSame('included', $addon->membershipPlans()->find($plan->id)->pivot->mode);
    }

    #[Test]
    public function it_deletes_an_addon(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $addon = Addon::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($owner)
            ->delete(route('contracts.addons.destroy', $addon))
            ->assertRedirect(route('contracts.addons.index'));

        $this->assertSoftDeleted($addon);
    }

    #[Test]
    public function it_forbids_managing_addons_of_another_gym(): void
    {
        [$owner] = $this->ownerWithGym();
        $foreignGym = Gym::factory()->create();
        $foreignAddon = Addon::factory()->create(['gym_id' => $foreignGym->id]);

        $this->actingAs($owner)
            ->delete(route('contracts.addons.destroy', $foreignAddon))
            ->assertForbidden();
    }
}
