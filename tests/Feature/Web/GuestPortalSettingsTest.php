<?php

namespace Tests\Feature\Web;

use App\Models\GuestProduct;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GuestPortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;

        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $this->owner->id]);

        $this->owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $this->owner->fresh();
    }

    private function product(Gym $gym, array $attributes = []): GuestProduct
    {
        return GuestProduct::create(array_merge([
            'gym_id' => $gym->id,
            'type' => GuestProduct::TYPE_VISIT_CARD,
            'name' => '10er-Karte',
            'price' => 80,
            'quantity' => 10,
        ], $attributes));
    }

    #[Test]
    public function the_owner_can_switch_the_guest_portal_on(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.guest-portal.update'), ['guest_enabled' => true])
            ->assertOk()
            ->assertJsonPath('guest_enabled', true);

        $this->assertTrue($this->gym->fresh()->guest_enabled);
    }

    #[Test]
    public function only_products_of_the_current_gym_are_listed(): void
    {
        $this->product($this->gym);
        $this->product(Gym::factory()->create(), ['name' => 'Fremd']);

        $this->actingAs($this->owner)
            ->getJson(route('settings.guest-portal.index'))
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', '10er-Karte');
    }

    #[Test]
    public function a_day_pass_always_grants_one_day(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('settings.guest-portal.products.store'), [
                'type' => GuestProduct::TYPE_DAY_PASS,
                'name' => 'Tageskarte',
                'price' => 12.5,
                'quantity' => 5,
            ])
            ->assertCreated();

        $product = GuestProduct::sole();
        $this->assertSame($this->gym->id, $product->gym_id);
        $this->assertSame(1, $product->quantity);
        $this->assertTrue($product->active);
    }

    #[Test]
    public function minutes_and_entries_need_a_quantity(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('settings.guest-portal.products.store'), [
                'type' => GuestProduct::TYPE_SOLARIUM_MINUTES,
                'name' => 'Solarium',
                'price' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    #[Test]
    public function the_type_of_an_existing_product_cannot_change(): void
    {
        $product = $this->product($this->gym);

        $this->actingAs($this->owner)
            ->putJson(route('settings.guest-portal.products.update', $product), [
                'type' => GuestProduct::TYPE_DAY_PASS,
                'name' => '10er-Karte',
                'price' => 80,
                'quantity' => 10,
            ])
            ->assertUnprocessable();

        $this->actingAs($this->owner)
            ->putJson(route('settings.guest-portal.products.update', $product), [
                'name' => '10er-Karte',
                'price' => 75,
                'quantity' => 10,
                'active' => false,
            ])
            ->assertOk();

        $product->refresh();
        $this->assertSame('75.00', $product->price);
        $this->assertFalse($product->active);
    }

    #[Test]
    public function products_of_another_gym_cannot_be_changed(): void
    {
        $foreign = $this->product(Gym::factory()->create());

        $this->actingAs($this->owner)
            ->putJson(route('settings.guest-portal.products.update', $foreign), [
                'name' => 'Übernommen',
                'price' => 1,
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->assertSame('10er-Karte', $foreign->fresh()->name);
    }
}
