<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'membership_plan_id' => MembershipPlan::factory(),
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => null,
            'status' => 'active',
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'cancellation_date' => now()->toDateString(),
        ]);
    }
}
