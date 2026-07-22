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

class MollieWebhookUrlModeTest extends TestCase
{
    use RefreshDatabase;

    private const OAUTH_TOKEN = 'access_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const LIVE_URL = 'https://my.gymportal.io/api/v1/public/mollie/webhook';

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

    #[Test]
    public function test_and_live_mode_resolve_to_distinct_urls(): void
    {
        $live = MollieService::resolveWebhookUrl(self::LIVE_URL, false);
        $test = MollieService::resolveWebhookUrl(self::LIVE_URL, true);

        $this->assertSame(self::LIVE_URL, $live);
        $this->assertNotSame($live, $test);

        // The test URL only adds a query parameter, the path stays identical
        $this->assertSame(
            parse_url($live, PHP_URL_PATH),
            parse_url($test, PHP_URL_PATH)
        );
        $this->assertStringContainsString('?', $test);
    }

    #[Test]
    public function the_test_webhook_url_still_reaches_the_public_webhook_route(): void
    {
        $testUrl = MollieService::resolveWebhookUrl(self::LIVE_URL, true);

        // Mollie posts to the URL it has registered, so that exact URL must be routable
        $this->postJson($testUrl, ['resource' => 'event', 'type' => 'hook.ping'])
            ->assertOk();
    }

    #[Test]
    public function the_test_mode_marker_is_never_appended_twice(): void
    {
        // The wizard already displays and submits the marked URL
        $alreadyMarked = MollieService::resolveWebhookUrl(self::LIVE_URL, true);

        $this->assertSame($alreadyMarked, MollieService::resolveWebhookUrl($alreadyMarked, true));
        $this->assertSame(1, substr_count(MollieService::resolveWebhookUrl($alreadyMarked, true), 'mode=test'));
    }

    #[Test]
    public function an_existing_query_string_is_preserved(): void
    {
        $url = MollieService::resolveWebhookUrl('https://my.gymportal.io/hook?tenant=42', true);

        $this->assertStringContainsString('tenant=42', $url);
        $this->assertStringContainsString('&', $url);
    }

    #[Test]
    public function it_registers_the_test_webhook_under_the_marked_url(): void
    {
        [$owner] = $this->ownerWithGym();

        Http::fake([
            'api.mollie.com/v2/webhooks*' => Http::sequence()
                ->push(['_embedded' => ['webhooks' => []]])
                ->push(['id' => 'hook_test']),
        ]);

        $this->actingAs($owner)
            ->postJson(route('v1.mollie.save-config'), [
                'api_key' => 'test_'.str_repeat('b', 30),
                'oauth_token' => self::OAUTH_TOKEN,
                'test_mode' => true,
                'enabled_methods' => ['creditcard'],
                'webhook_url' => self::LIVE_URL,
            ])
            ->assertOk();

        Http::assertSent(function (ClientRequest $request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/v2/webhooks')
                && str_contains($request['url'] ?? '', 'mode=test');
        });
    }

    #[Test]
    public function it_registers_a_single_marker_when_the_client_submits_the_marked_url(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        Http::fake([
            'api.mollie.com/v2/webhooks*' => Http::sequence()
                ->push(['_embedded' => ['webhooks' => []]])
                ->push(['id' => 'hook_test']),
        ]);

        $this->actingAs($owner)
            ->postJson(route('v1.mollie.save-config'), [
                'api_key' => 'test_'.str_repeat('b', 30),
                'oauth_token' => self::OAUTH_TOKEN,
                'test_mode' => true,
                'enabled_methods' => ['creditcard'],
                // Exactly what the wizard sends once it displays the marked URL
                'webhook_url' => self::LIVE_URL.'?mode=test',
            ])
            ->assertOk();

        Http::assertSent(function (ClientRequest $request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/v2/webhooks')
                && substr_count($request['url'] ?? '', 'mode=test') === 1;
        });

        $this->assertSame('hook_test', $gym->fresh()->mollie_config['webhook_id']);
    }

    #[Test]
    public function it_repoints_a_legacy_test_webhook_to_the_marked_url(): void
    {
        [$owner] = $this->ownerWithGym();

        // A test webhook registered before the mode marker existed
        Http::fake([
            'api.mollie.com/v2/webhooks*' => Http::response([
                '_embedded' => [
                    'webhooks' => [
                        [
                            'id' => 'hook_legacy',
                            'url' => self::LIVE_URL,
                            'name' => MollieService::webhookName(true),
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($owner)
            ->postJson(route('v1.mollie.save-config'), [
                'api_key' => 'test_'.str_repeat('b', 30),
                'oauth_token' => self::OAUTH_TOKEN,
                'test_mode' => true,
                'enabled_methods' => ['creditcard'],
                'webhook_url' => self::LIVE_URL,
            ])
            ->assertOk();

        Http::assertSent(function (ClientRequest $request) {
            return $request->method() === 'PATCH'
                && str_contains($request->url(), '/v2/webhooks/hook_legacy')
                && str_contains($request['url'] ?? '', 'mode=test');
        });
    }
}
