<?php

namespace Database\Factories;

use App\Models\Addon;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    protected $model = Addon::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 5, 99),
            'price_display' => Addon::PRICE_DISPLAY_MONTHLY,
            'service_type' => Addon::SERVICE_TYPE_ADDITIONAL,
            'billing_type' => Addon::BILLING_TYPE_ONE_TIME,
            'trial_rest_of_month' => false,
            'usage_period' => null,
            'usage_duration' => null,
            'usage_duration_unit' => null,
            'quota_amount' => null,
            'quota_interval' => null,
            'settled_via_device' => false,
            'payment_method' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * A recurring service billed monthly in sync with the membership fee.
     */
    public function recurring(): static
    {
        return $this->state(fn () => [
            'billing_type' => Addon::BILLING_TYPE_RECURRING,
        ]);
    }

    /**
     * Shows the computed weekly price in the widget instead of the monthly one.
     */
    public function weeklyPriceDisplay(): static
    {
        return $this->state(fn () => [
            'billing_type' => Addon::BILLING_TYPE_RECURRING,
            'price_display' => Addon::PRICE_DISPLAY_WEEKLY,
        ]);
    }

    /**
     * A usage service with an unlimited quota, settled via a device —
     * e.g. the drinks flat rate.
     */
    public function usageFlatRate(): static
    {
        return $this->state(fn () => [
            'service_type' => Addon::SERVICE_TYPE_USAGE,
            'billing_type' => Addon::BILLING_TYPE_RECURRING,
            'usage_period' => Addon::USAGE_PERIOD_FULL_DAY,
            'quota_amount' => null,
            'quota_interval' => null,
            'settled_via_device' => true,
        ]);
    }

    /**
     * A usage service limited to a number of units per interval.
     */
    public function usageLimited(int $amount = 8, string $interval = 'month'): static
    {
        return $this->state(fn () => [
            'service_type' => Addon::SERVICE_TYPE_USAGE,
            'billing_type' => Addon::BILLING_TYPE_RECURRING,
            'usage_period' => Addon::USAGE_PERIOD_FULL_DAY,
            'quota_amount' => $amount,
            'quota_interval' => $interval,
            'settled_via_device' => true,
        ]);
    }
}
