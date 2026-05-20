<?php

namespace Database\Factories;

use App\Models\LoginCode;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginCode>
 */
class LoginCodeFactory extends Factory
{
    protected $model = LoginCode::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'code' => str_pad((string) $this->faker->unique()->numberBetween(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(LoginCode::EXPIRY_MINUTES),
            'used' => false,
            'used_at' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'used' => true,
            'used_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }
}
