<?php

namespace Tests\Feature\Widget;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the origin allowlist of the embeddable widget: enforcement in
 * WidgetAuthMiddleware and storage via the widget settings endpoint.
 */
class WidgetAllowedOriginsTest extends TestCase
{
    use RefreshDatabase;

    private function gymWithOrigins(?array $origins): Gym
    {
        return Gym::factory()->create([
            'widget_enabled' => true,
            'widget_settings' => $origins === null ? null : ['security' => ['allowed_origins' => $origins]],
        ]);
    }

    private function requestPlans(Gym $gym, ?string $origin)
    {
        $headers = [
            'X-API-Key' => $gym->api_key,
            'X-Studio-ID' => (string) $gym->id,
            'X-Widget-Session' => 'test-session',
        ];

        if ($origin !== null) {
            $headers['Origin'] = $origin;
        }

        return $this->withHeaders($headers)->getJson('/api/widget/markup/plans');
    }

    #[Test]
    public function a_listed_origin_is_served(): void
    {
        $gym = $this->gymWithOrigins(['https://www.studio.de']);

        $this->requestPlans($gym, 'https://www.studio.de')->assertOk();
    }

    #[Test]
    public function a_foreign_origin_is_rejected(): void
    {
        $gym = $this->gymWithOrigins(['https://www.studio.de']);

        $this->requestPlans($gym, 'https://evil.example')
            ->assertForbidden()
            ->assertJsonPath('error', 'Origin nicht erlaubt');
    }

    #[Test]
    public function the_wildcard_allows_any_origin(): void
    {
        $gym = $this->gymWithOrigins(['*']);

        $this->requestPlans($gym, 'https://anywhere.example')->assertOk();
    }

    #[Test]
    public function requests_without_origin_are_served(): void
    {
        $gym = $this->gymWithOrigins(['https://www.studio.de']);

        $this->requestPlans($gym, null)->assertOk();
    }

    #[Test]
    public function gyms_without_configured_origins_keep_accepting_any_origin(): void
    {
        $gym = $this->gymWithOrigins(null);

        $this->requestPlans($gym, 'https://anywhere.example')->assertOk();
    }

    #[Test]
    public function the_allowlist_of_another_gym_does_not_apply(): void
    {
        $this->gymWithOrigins(['https://www.other-studio.de']);
        $gym = $this->gymWithOrigins(['https://www.studio.de']);

        $this->requestPlans($gym, 'https://www.other-studio.de')->assertForbidden();
    }

    #[Test]
    public function saving_settings_normalizes_the_origins(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->putJson(route('admin.widget.update'), $this->settingsPayload([
                'www.studio.de',
                'https://www.studio.de/kontakt',
                'HTTPS://Studio.de',
            ]))
            ->assertOk()
            ->assertJsonPath('gym.widget_settings.security.allowed_origins', [
                'https://www.studio.de',
                'https://studio.de',
            ]);

        $this->assertSame(
            ['https://www.studio.de', 'https://studio.de'],
            $gym->fresh()->widget_settings['security']['allowed_origins']
        );
    }

    #[Test]
    public function saving_settings_rejects_invalid_origins(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->putJson(route('admin.widget.update'), $this->settingsPayload(['ftp://studio.de']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('widget_settings.security.allowed_origins.0');
    }

    #[Test]
    public function saving_settings_rejects_an_empty_list(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->putJson(route('admin.widget.update'), $this->settingsPayload([]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('widget_settings.security.allowed_origins');
    }

    /**
     * @return array{0: User, 1: Gym}
     */
    private function ownerWithGym(): array
    {
        $role = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return [$owner->fresh(), $gym];
    }

    private function settingsPayload(array $origins): array
    {
        return [
            'widget_enabled' => true,
            'widget_settings' => [
                'colors' => ['primary' => '#3b82f6'],
                'texts' => ['title' => 'Wähle deinen Tarif'],
                'security' => ['allowed_origins' => $origins],
            ],
        ];
    }
}
