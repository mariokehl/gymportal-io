<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\MemberAccessConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberAccessConfig>
 */
class MemberAccessConfigFactory extends Factory
{
    protected $model = MemberAccessConfig::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'qr_code_enabled' => true,
            'nfc_enabled' => false,
        ];
    }

    public function withStaticLoginCode(string $code = '123456'): static
    {
        return $this->state(fn () => ['static_login_code' => $code]);
    }
}
