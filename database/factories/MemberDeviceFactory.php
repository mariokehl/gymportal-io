<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MemberDevice>
 */
class MemberDeviceFactory extends Factory
{
    protected $model = MemberDevice::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'device_token' => (string) Str::uuid(),
            'device_name' => 'iPhone',
            'last_used_at' => now(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
