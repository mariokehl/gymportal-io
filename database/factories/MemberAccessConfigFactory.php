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
        // static_login_code is intentionally not fillable — the factory writes
        // the attribute directly, which bypasses mass assignment.
        return $this->afterMaking(fn (MemberAccessConfig $config) => $config->static_login_code = $code)
            ->afterCreating(function (MemberAccessConfig $config) use ($code) {
                $config->static_login_code = $code;
                $config->save();
            });
    }
}
