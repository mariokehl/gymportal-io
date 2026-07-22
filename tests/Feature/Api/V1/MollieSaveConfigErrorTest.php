<?php

namespace Tests\Feature\Api\V1;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use App\Services\MollieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MollieSaveConfigErrorTest extends TestCase
{
    use RefreshDatabase;

    private const OAUTH_TOKEN = 'access_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

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

    private function saveConfigPayload(): array
    {
        return [
            'api_key' => 'test_'.str_repeat('b', 30),
            'oauth_token' => self::OAUTH_TOKEN,
            'test_mode' => true,
            'enabled_methods' => ['creditcard'],
        ];
    }

    #[Test]
    public function it_returns_the_readable_mollie_detail_when_creating_the_webhook_fails(): void
    {
        [$owner] = $this->ownerWithGym();

        Http::fake([
            // Lookup finds nothing, creation is then rejected by Mollie
            'api.mollie.com/v2/webhooks*' => Http::sequence()
                ->push(['_embedded' => ['webhooks' => []]])
                ->push([
                    'status' => 422,
                    'title' => 'Unprocessable Entity',
                    'detail' => "Webhook subscription with name 'Webhook #1' already exists.",
                ], 422),
        ]);

        $response = $this->actingAs($owner)
            ->postJson(route('v1.mollie.save-config'), $this->saveConfigPayload())
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Die Mollie-Konfiguration konnte nicht gespeichert werden.',
                'detail' => "Webhook subscription with name 'Webhook #1' already exists.",
            ]);

        // The raw API body must not leak into the user facing message
        $this->assertStringNotContainsString('_links', $response->json('message'));
    }

    #[Test]
    public function it_reuses_a_webhook_matched_by_name_when_the_url_differs(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        Http::fake([
            'api.mollie.com/v2/webhooks*' => Http::response([
                '_embedded' => [
                    'webhooks' => [
                        [
                            'id' => 'hook_stale',
                            'url' => 'https://old-tunnel.example/api/v1/public/mollie/webhook',
                            'name' => MollieService::webhookName(true),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($owner)
            ->postJson(route('v1.mollie.save-config'), $this->saveConfigPayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('hook_stale', $gym->fresh()->mollie_config['webhook_id']);

        // Stale URL gets re-pointed instead of creating a duplicate
        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/v2/webhooks/hook_stale'));

        Http::assertNotSent(fn (ClientRequest $request) => $request->method() === 'POST'
            && str_contains($request->url(), '/v2/webhooks'));
    }

    #[Test]
    public function it_returns_validation_errors_for_an_invalid_payload(): void
    {
        [$owner] = $this->ownerWithGym();

        Http::fake();

        $this->actingAs($owner)
            ->postJson(route('v1.mollie.save-config'), ['api_key' => 'too-short'])
            ->assertStatus(422)
            ->assertJsonStructure(['success', 'errors' => ['api_key']]);

        Http::assertNothingSent();
    }
}
