<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 19, 99),
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'is_free_trial_plan' => false,
            'commitment_months' => 0,
            'cancellation_period' => 30,
            'cancellation_period_unit' => 'days',
        ];
    }

    public function freeTrial(): static
    {
        return $this->state(fn () => [
            'is_free_trial_plan' => true,
            'price' => 0,
            'trial_period_days' => 7,
        ]);
    }
}
