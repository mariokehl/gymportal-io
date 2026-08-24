<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanDiscountPhase;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipPlanDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipPlanDiscountPhaseTest extends TestCase
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
     * Valid plan payload; discount fields are merged in per test.
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

    #[Test]
    public function it_stores_discount_phases_in_order(): void
    {
        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload([
                'discounts_enabled' => true,
                'discount_phases' => [
                    ['duration_months' => 3, 'price' => '19.95', 'original_price' => ''],
                    ['duration_months' => 9, 'price' => '34.95', 'original_price' => '49.95'],
                ],
            ]))
            ->assertRedirect();

        $plan = MembershipPlan::where('gym_id', $this->gym->id)->firstOrFail();
        $phases = $plan->discountPhases()->get();

        $this->assertTrue($plan->discounts_enabled);
        $this->assertCount(2, $phases);

        $this->assertSame(0, $phases[0]->sort_order);
        $this->assertSame(3, $phases[0]->duration_months);
        $this->assertSame('19.95', $phases[0]->price);
        $this->assertNull($phases[0]->original_price);

        $this->assertSame(1, $phases[1]->sort_order);
        $this->assertSame(9, $phases[1]->duration_months);
        $this->assertSame('49.95', $phases[1]->original_price);
    }

    #[Test]
    public function it_refuses_discounts_on_a_non_monthly_billing_cycle(): void
    {
        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload([
                'billing_cycle' => 'yearly',
                'discounts_enabled' => true,
                'discount_phases' => [
                    ['duration_months' => 3, 'price' => '19.95', 'original_price' => ''],
                ],
            ]))
            ->assertRedirect();

        $plan = MembershipPlan::where('gym_id', $this->gym->id)->firstOrFail();

        $this->assertFalse($plan->discounts_enabled);
        $this->assertSame(0, $plan->discountPhases()->count());
    }

    #[Test]
    public function it_drops_the_phases_when_the_cycle_leaves_monthly(): void
    {
        $plan = $this->plan(['discounts_enabled' => true, 'billing_cycle' => 'monthly']);
        $plan->discountPhases()->createMany([
            ['sort_order' => 0, 'duration_months' => 3, 'price' => 19.95],
        ]);

        $this->actingAs($this->owner)
            ->put(route('contracts.update', $plan), $this->planPayload([
                'billing_cycle' => 'quarterly',
                'discounts_enabled' => true,
                'discount_phases' => [
                    ['duration_months' => 3, 'price' => '19.95', 'original_price' => ''],
                ],
            ]))
            ->assertRedirect();

        $plan = $plan->fresh();

        $this->assertFalse($plan->discounts_enabled);
        $this->assertSame(0, $plan->discountPhases()->count());
    }

    #[Test]
    public function saving_an_unchanged_ladder_keeps_its_version(): void
    {
        $plan = $this->plan(['discounts_enabled' => true, 'billing_cycle' => 'monthly']);
        $service = app(MembershipPlanDiscountService::class);

        $rows = [
            ['duration_months' => 3, 'price' => '19.95', 'original_price' => ''],
            ['duration_months' => 9, 'price' => '34.95', 'original_price' => ''],
        ];

        $service->sync($plan, $rows);
        $first = $plan->fresh()->discountPhases()->first()->version_key;

        $service->sync($plan->fresh(), $rows);
        $second = $plan->fresh()->discountPhases()->first()->version_key;

        $this->assertNotNull($first);
        $this->assertSame($first, $second);
        $this->assertSame(2, $plan->fresh()->discountPhases()->count());
    }

    #[Test]
    public function changing_the_ladder_mints_a_new_version(): void
    {
        $plan = $this->plan(['discounts_enabled' => true, 'billing_cycle' => 'monthly']);
        $service = app(MembershipPlanDiscountService::class);

        $service->sync($plan, [['duration_months' => 3, 'price' => '19.95', 'original_price' => '']]);
        $first = $plan->fresh()->discountPhases()->first()->version_key;

        $service->sync($plan->fresh(), [['duration_months' => 6, 'price' => '9.99', 'original_price' => '']]);
        $second = $plan->fresh()->discountPhases()->first()->version_key;

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function a_replaced_phase_is_kept_so_its_version_stays_resolvable(): void
    {
        $plan = $this->plan(['discounts_enabled' => true, 'billing_cycle' => 'monthly']);
        $service = app(MembershipPlanDiscountService::class);

        $service->sync($plan, [['duration_months' => 3, 'price' => '19.95', 'original_price' => '']]);
        $signedVersion = $plan->fresh()->discountPhases()->first()->version_key;

        $service->sync($plan->fresh(), [['duration_months' => 6, 'price' => '9.99', 'original_price' => '']]);

        $historic = MembershipPlanDiscountPhase::withTrashed()
            ->where('version_key', $signedVersion)
            ->get();

        $this->assertCount(1, $historic);
        $this->assertSame('19.95', $historic->first()->price);
    }

    #[Test]
    public function it_replaces_existing_phases_on_update(): void
    {
        $plan = $this->plan(['discounts_enabled' => true]);
        $plan->discountPhases()->createMany([
            ['sort_order' => 0, 'duration_months' => 6, 'price' => 9.99],
            ['sort_order' => 1, 'duration_months' => 6, 'price' => 19.99],
        ]);

        $this->actingAs($this->owner)
            ->put(route('contracts.update', $plan), $this->planPayload([
                'discounts_enabled' => true,
                'discount_phases' => [
                    ['duration_months' => 1, 'price' => '1.00', 'original_price' => ''],
                ],
            ]))
            ->assertRedirect();

        $phases = $plan->fresh()->discountPhases()->get();

        $this->assertCount(1, $phases);
        $this->assertSame(1, $phases[0]->duration_months);
        $this->assertSame('1.00', $phases[0]->price);
    }

    #[Test]
    public function it_discards_phases_when_discounts_are_disabled(): void
    {
        $plan = $this->plan(['discounts_enabled' => true]);
        $plan->discountPhases()->create([
            'sort_order' => 0,
            'duration_months' => 3,
            'price' => 19.95,
        ]);

        $this->actingAs($this->owner)
            ->put(route('contracts.update', $plan), $this->planPayload([
                'discounts_enabled' => false,
                'discount_phases' => [
                    ['duration_months' => 3, 'price' => '19.95', 'original_price' => ''],
                ],
            ]))
            ->assertRedirect();

        $plan->refresh();

        $this->assertFalse($plan->discounts_enabled);
        $this->assertCount(0, $plan->discountPhases()->get());
    }

    #[Test]
    public function it_rejects_invalid_phase_rows(): void
    {
        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload([
                'discounts_enabled' => true,
                'discount_phases' => [
                    ['duration_months' => 0, 'price' => '19.95'],
                ],
            ]))
            ->assertSessionHasErrors('discount_phases.0.duration_months');

        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload([
                'discounts_enabled' => true,
                'discount_phases' => [
                    ['duration_months' => 3, 'price' => '-5'],
                ],
            ]))
            ->assertSessionHasErrors('discount_phases.0.price');
    }

    #[Test]
    public function it_allows_saving_a_plan_without_any_phases(): void
    {
        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload())
            ->assertRedirect();

        $plan = MembershipPlan::where('gym_id', $this->gym->id)->firstOrFail();

        $this->assertFalse($plan->discounts_enabled);
        $this->assertCount(0, $plan->discountPhases()->get());
    }
}
