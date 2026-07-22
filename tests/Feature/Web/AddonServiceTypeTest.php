<?php

namespace Tests\Feature\Web;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddonServiceTypeTest extends TestCase
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

    /**
     * Base payload for a recurring usage service with an unlimited quota.
     */
    private function drinksFlatRatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Getränke-Flatrate',
            'description' => 'Unbegrenzt Wasser, Kaffee & Softdrinks am Spender.',
            'price' => 8.62,
            'service_type' => Addon::SERVICE_TYPE_USAGE,
            'billing_type' => Addon::BILLING_TYPE_RECURRING,
            'trial_rest_of_month' => true,
            'usage_period' => Addon::USAGE_PERIOD_FULL_DAY,
            'settled_via_device' => true,
            'is_active' => true,
        ], $overrides);
    }

    #[Test]
    public function it_creates_a_recurring_usage_service_with_an_unlimited_quota(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->drinksFlatRatePayload())
            ->assertRedirect(route('contracts.addons.index'));

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertTrue($addon->isUsageService());
        $this->assertTrue($addon->isRecurring());
        $this->assertTrue($addon->trial_rest_of_month);
        $this->assertTrue($addon->settled_via_device);
        $this->assertSame(Addon::USAGE_PERIOD_FULL_DAY, $addon->usage_period);

        // No quota amount means an unlimited flat rate.
        $this->assertTrue($addon->hasUnlimitedQuota());
        $this->assertNull($addon->quota_interval);
    }

    #[Test]
    public function it_creates_a_usage_service_with_a_limited_quota(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->drinksFlatRatePayload([
                'name' => 'Sauna-Paket',
                'quota_amount' => 8,
                'quota_interval' => 'month',
            ]))
            ->assertRedirect(route('contracts.addons.index'));

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertSame(8, $addon->quota_amount);
        $this->assertSame('month', $addon->quota_interval);
        $this->assertFalse($addon->hasUnlimitedQuota());
    }

    #[Test]
    public function it_stores_the_duration_only_for_a_fixed_usage_period(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->drinksFlatRatePayload([
                'usage_period' => Addon::USAGE_PERIOD_FIXED,
                'usage_duration' => 2,
                'usage_duration_unit' => 'hours',
            ]))
            ->assertRedirect(route('contracts.addons.index'));

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertSame(2, $addon->usage_duration);
        $this->assertSame('hours', $addon->usage_duration_unit);
    }

    #[Test]
    public function it_requires_a_duration_for_a_fixed_usage_period(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->drinksFlatRatePayload([
                'usage_period' => Addon::USAGE_PERIOD_FIXED,
            ]))
            ->assertSessionHasErrors(['usage_duration', 'usage_duration_unit']);
    }

    #[Test]
    public function it_requires_a_quota_interval_when_a_quota_amount_is_given(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->drinksFlatRatePayload([
                'quota_amount' => 10,
            ]))
            ->assertSessionHasErrors('quota_interval');
    }

    #[Test]
    public function it_requires_a_usage_period_for_a_usage_service(): void
    {
        [$owner] = $this->ownerWithGym();

        $payload = $this->drinksFlatRatePayload();
        unset($payload['usage_period']);

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $payload)
            ->assertSessionHasErrors('usage_period');
    }

    #[Test]
    public function it_discards_usage_fields_for_an_additional_service(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        // Usage fields sent alongside an additional service must be ignored.
        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->drinksFlatRatePayload([
                'name' => 'Trainereinweisung',
                'service_type' => Addon::SERVICE_TYPE_ADDITIONAL,
                'billing_type' => Addon::BILLING_TYPE_ONE_TIME,
                'quota_amount' => 10,
                'quota_interval' => 'month',
            ]))
            ->assertRedirect(route('contracts.addons.index'));

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertFalse($addon->isUsageService());
        $this->assertNull($addon->usage_period);
        $this->assertNull($addon->quota_amount);
        $this->assertNull($addon->quota_interval);
        $this->assertFalse($addon->settled_via_device);

        // The trial only applies to recurring billing.
        $this->assertFalse($addon->trial_rest_of_month);
    }

    #[Test]
    public function it_clears_usage_fields_when_switching_to_an_additional_service(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $addon = Addon::factory()->usageLimited()->create([
            'gym_id' => $gym->id,
            'trial_rest_of_month' => true,
        ]);

        $this->actingAs($owner)
            ->put(route('contracts.addons.update', $addon), [
                'name' => $addon->name,
                'price' => $addon->price,
                'service_type' => Addon::SERVICE_TYPE_ADDITIONAL,
                'billing_type' => Addon::BILLING_TYPE_ONE_TIME,
                'is_active' => true,
            ])
            ->assertRedirect(route('contracts.addons.index'));

        $addon->refresh();

        $this->assertNull($addon->usage_period);
        $this->assertNull($addon->quota_amount);
        $this->assertNull($addon->quota_interval);
        $this->assertFalse($addon->settled_via_device);
        $this->assertFalse($addon->trial_rest_of_month);
    }

    #[Test]
    public function it_defaults_to_a_one_time_additional_service(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        // Clients that predate the usage fields must keep working.
        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), [
                'name' => 'Handtuch-Service',
                'price' => 5.00,
                'is_active' => true,
            ])
            ->assertRedirect(route('contracts.addons.index'));

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertSame(Addon::SERVICE_TYPE_ADDITIONAL, $addon->service_type);
        $this->assertSame(Addon::BILLING_TYPE_ONE_TIME, $addon->billing_type);
    }

    #[Test]
    public function it_rejects_an_unknown_service_type(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->drinksFlatRatePayload([
                'service_type' => 'invalid',
            ]))
            ->assertSessionHasErrors('service_type');
    }
}
