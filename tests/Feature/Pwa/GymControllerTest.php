<?php

namespace Tests\Feature\Pwa;

use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GymControllerTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // index
    // ------------------------------------------------------------------

    #[Test]
    public function index_returns_only_pwa_enabled_gyms(): void
    {
        $enabled = Gym::factory()->count(2)->create();
        Gym::factory()->pwaDisabled()->create();

        $response = $this->getJson('/api/pwa/gyms')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'name', 'slug', 'logo_url', 'primary_color']],
            ]);

        $this->assertCount(2, $response->json('data'));
        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        sort($returnedIds);
        $expected = $enabled->pluck('id')->sort()->values()->all();
        $this->assertSame($expected, $returnedIds);
    }

    #[Test]
    public function index_returns_empty_when_no_pwa_gyms_exist(): void
    {
        Gym::factory()->pwaDisabled()->create();

        $this->getJson('/api/pwa/gyms')
            ->assertOk()
            ->assertJson(['success' => true, 'data' => []]);
    }

    // ------------------------------------------------------------------
    // show
    // ------------------------------------------------------------------

    #[Test]
    public function show_returns_full_member_app_data(): void
    {
        $gym = Gym::factory()->create([
            'name' => 'Test Studio',
            'slug' => 'test-studio',
        ]);

        $this->getJson('/api/pwa/gyms/' . $gym->slug)
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'slug', 'theme', 'pwa_enabled', 'legal_urls'],
            ])
            ->assertJsonPath('data.slug', 'test-studio')
            ->assertJsonPath('data.name', 'Test Studio');
    }

    #[Test]
    public function show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/pwa/gyms/missing-slug')
            ->assertStatus(404)
            ->assertJson(['error_code' => 'GYM_NOT_FOUND']);
    }

    #[Test]
    public function show_returns_404_when_pwa_disabled(): void
    {
        $gym = Gym::factory()->pwaDisabled()->create();

        $this->getJson('/api/pwa/gyms/' . $gym->slug)
            ->assertStatus(404)
            ->assertJson(['error_code' => 'GYM_NOT_FOUND']);
    }

    // ------------------------------------------------------------------
    // theme
    // ------------------------------------------------------------------

    #[Test]
    public function theme_returns_theme_payload(): void
    {
        $gym = Gym::factory()->create(['primary_color' => '#123456']);

        $this->getJson('/api/pwa/gyms/' . $gym->slug . '/theme')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.primary_color', '#123456');
    }

    #[Test]
    public function theme_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/pwa/gyms/missing/theme')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ------------------------------------------------------------------
    // manifest
    // ------------------------------------------------------------------

    #[Test]
    public function manifest_returns_manifest_with_correct_content_type(): void
    {
        $gym = Gym::factory()->create();

        $this->getJson('/api/pwa/gyms/' . $gym->slug . '/manifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');
    }

    #[Test]
    public function manifest_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/pwa/gyms/missing/manifest')
            ->assertStatus(404)
            ->assertJson(['error' => 'Gym not found']);
    }

    // ------------------------------------------------------------------
    // related
    // ------------------------------------------------------------------

    #[Test]
    public function related_returns_sibling_gyms_with_same_owner(): void
    {
        $owner = \App\Models\User::factory()->create();
        $primary = Gym::factory()->create(['owner_id' => $owner->id]);
        Gym::factory()->count(2)->create(['owner_id' => $owner->id]);
        // Different owner - must not appear
        Gym::factory()->create();

        $response = $this->getJson('/api/pwa/gyms/' . $primary->slug . '/related')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [['id', 'name', 'slug', 'address', 'postal_code', 'city']],
            ]);

        // 3 gyms total under this owner (primary + 2 siblings)
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function related_excludes_pwa_disabled_siblings(): void
    {
        $owner = \App\Models\User::factory()->create();
        $primary = Gym::factory()->create(['owner_id' => $owner->id]);
        Gym::factory()->create(['owner_id' => $owner->id]);
        Gym::factory()->pwaDisabled()->create(['owner_id' => $owner->id]);

        $this->getJson('/api/pwa/gyms/' . $primary->slug . '/related')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function related_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/pwa/gyms/missing/related')
            ->assertStatus(404)
            ->assertJson(['error' => 'Gym not found']);
    }
}
