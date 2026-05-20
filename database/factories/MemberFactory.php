<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'gym_id' => Gym::factory(),
            'member_number' => 'M' . $this->faker->unique()->randomNumber(8),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'birth_date' => $this->faker->date('Y-m-d', '-20 years'),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'DE',
            'status' => 'active',
            'joined_date' => now()->subYear(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => 'paused']);
    }
}
