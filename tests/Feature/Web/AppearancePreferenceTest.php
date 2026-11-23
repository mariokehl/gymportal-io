<?php

namespace Tests\Feature\Web;

use App\Enums\ThemePreference;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppearancePreferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $user = User::factory()->create(['role_id' => $roleId]);
        $gym = Gym::factory()->create(['owner_id' => $user->id]);
        $user->update(['current_gym_id' => $gym->id]);
        $this->user = $user->fresh();
    }

    #[Test]
    public function new_users_follow_the_system_setting_by_default(): void
    {
        $this->assertSame(ThemePreference::System, $this->user->theme_preference);
    }

    #[Test]
    public function a_user_can_save_their_appearance(): void
    {
        $this->actingAs($this->user)
            ->from(route('profile.index'))
            ->put(route('profile.appearance.update'), ['theme_preference' => 'dark'])
            ->assertRedirect(route('profile.index'))
            ->assertSessionHas('success', 'Darstellung gespeichert.');

        $this->assertSame(ThemePreference::Dark, $this->user->fresh()->theme_preference);
    }

    #[Test]
    public function unknown_appearances_are_rejected(): void
    {
        $this->actingAs($this->user)
            ->from(route('profile.index'))
            ->put(route('profile.appearance.update'), ['theme_preference' => 'sepia'])
            ->assertSessionHasErrors('theme_preference');

        $this->assertSame(ThemePreference::System, $this->user->fresh()->theme_preference);
    }

    #[Test]
    public function guests_cannot_change_an_appearance(): void
    {
        $this->put(route('profile.appearance.update'), ['theme_preference' => 'dark'])
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function the_preference_is_shared_and_rendered_before_the_first_paint(): void
    {
        $this->user->update(['theme_preference' => ThemePreference::Dark]);

        $response = $this->actingAs($this->user)->get(route('profile.index'));

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.user.theme_preference', 'dark')
            ->where('user.theme_preference', 'dark'));

        $response->assertSee('data-theme-preference="dark"', false);
        $response->assertSee('class="dark"', false);
    }
}
