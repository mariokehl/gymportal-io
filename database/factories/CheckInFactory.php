<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    protected $model = CheckIn::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'gym_id' => Gym::factory(),
            'check_in_time' => now()->subMinutes(30),
            'check_out_time' => null,
            'check_in_method' => 'qr_code',
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attrs) => [
            'check_out_time' => now(),
        ]);
    }

    public function older(int $hours): static
    {
        return $this->state(fn () => [
            'check_in_time' => now()->subHours($hours),
        ]);
    }
}
