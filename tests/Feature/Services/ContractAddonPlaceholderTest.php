<?php

namespace Tests\Feature\Services;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ContractAddonPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    private function resolvePlaceholders(Membership $membership): array
    {
        $method = new ReflectionMethod(ContractService::class, 'getContractSpecificPlaceholders');
        $method->setAccessible(true);

        return $method->invoke(app(ContractService::class), $membership);
    }

    private function membershipWithAddons(array $addons): Membership
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
        ]);

        foreach ($addons as $addon) {
            $model = Addon::factory()->create(['gym_id' => $gym->id, 'name' => $addon['name']]);
            $membership->addons()->attach($model->id, [
                'mode' => $addon['mode'],
                'price' => $addon['price'],
            ]);
        }

        return $membership->fresh();
    }

    public function test_placeholder_lists_optional_and_included_addons(): void
    {
        $membership = $this->membershipWithAddons([
            ['name' => 'Trainereinweisung', 'mode' => 'optional', 'price' => 60],
            ['name' => 'Getränkeflat', 'mode' => 'included', 'price' => 0],
        ]);

        $placeholders = $this->resolvePlaceholders($membership);

        $this->assertArrayHasKey('[Zusatzleistungen]', $placeholders);
        $this->assertStringContainsString('<p>', $placeholders['[Zusatzleistungen]']);
        $this->assertStringContainsString('Trainereinweisung: 60,00 € einmalig<br>', $placeholders['[Zusatzleistungen]']);
        $this->assertStringContainsString('Getränkeflat: inklusive<br>', $placeholders['[Zusatzleistungen]']);
    }

    public function test_placeholder_shows_fallback_text_without_addons(): void
    {
        $membership = $this->membershipWithAddons([]);

        $placeholders = $this->resolvePlaceholders($membership);

        $this->assertSame('<p>Keine Zusatzleistungen gebucht.</p>', $placeholders['[Zusatzleistungen]']);
    }
}
