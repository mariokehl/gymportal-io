<?php

namespace Tests\Feature\Web;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddonPriceDisplayTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym}
     */
    private function ownerWithGym(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return [$owner->fresh(), $gym];
    }

    /**
     * Base payload for a recurring add-on shown with a weekly price.
     */
    private function weeklyDisplayPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Getränke-Flatrate',
            'price' => 8.62,
            'price_display' => Addon::PRICE_DISPLAY_WEEKLY,
            'service_type' => Addon::SERVICE_TYPE_ADDITIONAL,
            'billing_type' => Addon::BILLING_TYPE_RECURRING,
            'is_active' => true,
        ], $overrides);
    }

    #[Test]
    public function it_stores_the_weekly_price_display_for_a_recurring_addon(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->weeklyDisplayPayload())
            ->assertRedirect(route('contracts.addons.index'));

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertSame(Addon::PRICE_DISPLAY_WEEKLY, $addon->price_display);
        $this->assertTrue($addon->showsWeeklyPrice());
    }

    #[Test]
    public function it_derives_the_weekly_price_from_the_monthly_price(): void
    {
        $addon = Addon::factory()->weeklyPriceDisplay()->make(['price' => 8.62]);

        // 8.62 × 12 ÷ 52 = 1.9892… → 1.99
        $this->assertSame(1.99, $addon->weekly_price);
        $this->assertSame('1,99 €', $addon->formatted_weekly_price);

        // The monthly price stays the amount that is billed.
        $this->assertSame('8,62 €', $addon->formatted_price);
    }

    #[Test]
    public function it_defaults_to_the_monthly_price_display(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        // Clients that predate the price display field must keep working.
        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), [
                'name' => 'Handtuch-Service',
                'price' => 5.00,
                'is_active' => true,
            ])
            ->assertRedirect(route('contracts.addons.index'));

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertSame(Addon::PRICE_DISPLAY_MONTHLY, $addon->price_display);
        $this->assertFalse($addon->showsWeeklyPrice());
    }

    #[Test]
    public function it_forces_the_monthly_display_for_a_one_time_addon(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        // A weekly figure is only meaningful for monthly billing.
        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->weeklyDisplayPayload([
                'name' => 'Trainereinweisung',
                'billing_type' => Addon::BILLING_TYPE_ONE_TIME,
            ]))
            ->assertRedirect(route('contracts.addons.index'));

        $addon = Addon::where('gym_id', $gym->id)->firstOrFail();

        $this->assertSame(Addon::PRICE_DISPLAY_MONTHLY, $addon->price_display);
        $this->assertFalse($addon->showsWeeklyPrice());
    }

    #[Test]
    public function it_resets_the_weekly_display_when_switching_to_one_time_billing(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $addon = Addon::factory()->weeklyPriceDisplay()->create(['gym_id' => $gym->id]);

        $this->actingAs($owner)
            ->put(route('contracts.addons.update', $addon), [
                'name' => $addon->name,
                'price' => $addon->price,
                'service_type' => Addon::SERVICE_TYPE_ADDITIONAL,
                'billing_type' => Addon::BILLING_TYPE_ONE_TIME,
                'is_active' => true,
            ])
            ->assertRedirect(route('contracts.addons.index'));

        $addon->refresh();

        $this->assertSame(Addon::PRICE_DISPLAY_MONTHLY, $addon->price_display);
    }

    #[Test]
    public function it_rejects_an_unknown_price_display(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('contracts.addons.store'), $this->weeklyDisplayPayload([
                'price_display' => 'daily',
            ]))
            ->assertSessionHasErrors('price_display');
    }
}
