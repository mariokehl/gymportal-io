<?php

namespace Tests\Feature\Web;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GymSymbolSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $berlin;

    private Gym $hamburg;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;

        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->berlin = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Berlin']);
        $this->hamburg = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Hamburg']);

        $this->owner->update(['current_gym_id' => $this->berlin->id]);
        $this->owner = $this->owner->fresh();
    }

    #[Test]
    public function it_stores_an_emoji_symbol(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.gym.symbol.update', $this->berlin), [
                'symbol_type' => Gym::SYMBOL_TYPE_EMOJI,
                'symbol_emoji' => '🥊',
                'symbol_color' => '#0891b2',
            ])
            ->assertOk();

        $this->berlin->refresh();

        $this->assertSame(Gym::SYMBOL_TYPE_EMOJI, $this->berlin->symbol_type);
        $this->assertSame('🥊', $this->berlin->symbol_emoji);
        $this->assertSame('#0891b2', $this->berlin->symbol_color);
    }

    #[Test]
    public function it_drops_the_emoji_when_switching_back_to_the_initial(): void
    {
        $this->berlin->update([
            'symbol_type' => Gym::SYMBOL_TYPE_EMOJI,
            'symbol_emoji' => '🥊',
            'symbol_color' => '#0891b2',
        ]);

        $this->actingAs($this->owner)
            ->putJson(route('settings.gym.symbol.update', $this->berlin), [
                'symbol_type' => Gym::SYMBOL_TYPE_INITIAL,
                'symbol_emoji' => '🥊',
                'symbol_color' => '#dc2626',
            ])
            ->assertOk();

        $this->berlin->refresh();

        $this->assertSame(Gym::SYMBOL_TYPE_INITIAL, $this->berlin->symbol_type);
        $this->assertNull($this->berlin->symbol_emoji);
    }

    #[Test]
    public function it_rejects_an_emoji_outside_the_curated_palette(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.gym.symbol.update', $this->berlin), [
                'symbol_type' => Gym::SYMBOL_TYPE_EMOJI,
                'symbol_emoji' => '<script>alert(1)</script>',
                'symbol_color' => '#4f46e5',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('symbol_emoji');
    }

    #[Test]
    public function it_rejects_a_colour_outside_the_curated_palette(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.gym.symbol.update', $this->berlin), [
                'symbol_type' => Gym::SYMBOL_TYPE_INITIAL,
                'symbol_color' => '#123456',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('symbol_color');
    }

    #[Test]
    public function it_requires_an_emoji_for_the_emoji_symbol(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('settings.gym.symbol.update', $this->berlin), [
                'symbol_type' => Gym::SYMBOL_TYPE_EMOJI,
                'symbol_emoji' => null,
                'symbol_color' => '#4f46e5',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('symbol_emoji');
    }

    #[Test]
    public function a_member_without_manage_rights_cannot_change_the_symbol(): void
    {
        // Belongs to this organisation, but is not allowed to manage the gym.
        $staff = User::factory()->create(['current_gym_id' => $this->berlin->id]);

        $this->actingAs($staff->fresh())
            ->putJson(route('settings.gym.symbol.update', $this->berlin), [
                'symbol_type' => Gym::SYMBOL_TYPE_EMOJI,
                'symbol_emoji' => '🥊',
                'symbol_color' => '#4f46e5',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function a_user_cannot_change_the_symbol_of_another_organisation(): void
    {
        $otherOwner = User::factory()->create();
        $otherGym = Gym::factory()->create(['owner_id' => $otherOwner->id, 'name' => 'Fremdstudio']);

        $this->actingAs($this->owner)
            ->putJson(route('settings.gym.symbol.update', $otherGym), [
                'symbol_type' => Gym::SYMBOL_TYPE_EMOJI,
                'symbol_emoji' => '🥊',
                'symbol_color' => '#4f46e5',
            ])
            ->assertForbidden();

        $otherGym->refresh();

        $this->assertNull($otherGym->symbol_type);
    }

    #[Test]
    public function it_falls_back_to_the_initial_of_the_display_name(): void
    {
        $this->berlin->update(['display_name' => 'Berlin Mitte']);

        $symbol = $this->berlin->fresh()->getSymbol();

        $this->assertSame(Gym::SYMBOL_TYPE_INITIAL, $symbol['type']);
        $this->assertSame('B', $symbol['initial']);
        $this->assertSame(Gym::DEFAULT_SYMBOL_COLOR, $symbol['color']);
    }

    #[Test]
    public function it_falls_back_to_the_initial_when_the_emoji_is_missing(): void
    {
        $this->berlin->update(['symbol_type' => Gym::SYMBOL_TYPE_EMOJI, 'symbol_emoji' => null]);

        $this->assertSame(Gym::SYMBOL_TYPE_INITIAL, $this->berlin->fresh()->getSymbol()['type']);
    }

    #[Test]
    public function the_symbol_is_shared_with_the_switcher_for_every_organisation(): void
    {
        $this->berlin->update([
            'symbol_type' => Gym::SYMBOL_TYPE_EMOJI,
            'symbol_emoji' => '🥊',
            'symbol_color' => '#0891b2',
        ]);

        $shared = $this->sharedUser($this->owner->fresh());

        $this->assertSame('🥊', $shared['current_gym']['symbol']['emoji']);
        $this->assertSame('#0891b2', $shared['current_gym']['symbol']['color']);

        $symbols = collect($shared['all_gyms'])->keyBy('id');

        $this->assertSame('🥊', $symbols[$this->berlin->id]['symbol']['emoji']);
        $this->assertSame(Gym::SYMBOL_TYPE_INITIAL, $symbols[$this->hamburg->id]['symbol']['type']);
        $this->assertSame('F', $symbols[$this->hamburg->id]['symbol']['initial']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedUser(User $user): array
    {
        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $shared = (new HandleInertiaRequests)->share($request);

        return value($shared['auth']['user']);
    }
}
