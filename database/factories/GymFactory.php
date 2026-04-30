<?php

namespace Database\Factories;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Gym>
 */
class GymFactory extends Factory
{
    protected $model = Gym::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name . '-' . $this->faker->unique()->randomNumber(6)),
            'description' => $this->faker->sentence(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'DE',
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->companyEmail(),
            'owner_id' => User::factory(),
            'subscription_status' => 'active',
            'subscription_plan' => 'pro',
            'subscription_ends_at' => now()->addYear(),
            'trial_ends_at' => now()->addDays(30),
            'pwa_enabled' => true,
            'primary_color' => '#e11d48',
            'secondary_color' => '#64748b',
            'accent_color' => '#10b981',
            'scanner_secret_key' => Str::random(64),
        ];
    }

    public function pwaDisabled(): static
    {
        return $this->state(fn () => ['pwa_enabled' => false]);
    }

    public function pwaLoginDisabled(): static
    {
        return $this->state(fn () => [
            'pwa_settings' => ['pwa_login_disabled' => true],
        ]);
    }

    public function withoutActiveSubscription(): static
    {
        return $this->state(fn () => [
            'subscription_status' => 'inactive',
            'subscription_ends_at' => null,
            'trial_ends_at' => now()->subDay(),
        ]);
    }
}
