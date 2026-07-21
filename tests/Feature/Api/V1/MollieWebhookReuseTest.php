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

class MollieWebhookReuseTest extends TestCase
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

    private function saveConfigPayload(bool $testMode = true): array
    {
        return [
            'api_key' => 'test_'.str_repeat('b', 30),
            'oauth_token' => self::OAUTH_TOKEN,
            'test_mode' => $testMode,
            'enabled_methods' => ['creditcard'],
        ];
    }

    #[Test]
    public function it_adopts_an_existing_webhook_instead_of_creating_a_new_one(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        // Test mode registers a distinct URL, so the fake has to return exactly that one
        $webhookUrl = MollieService::resolveWebhookUrl(route('v1.public.mollie.webhook'), true);

        Http::fake([
            'api.mollie.com/v2/webhooks*' => Http::response([
                '_embedded' => [
                    'webhooks' => [
                        ['id' => 'hook_existing', 'url' => $webhookUrl, 'status' => 'enabled'],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($owner)
            ->postJson(route('v1.mollie.save-config'), $this->saveConfigPayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        // The existing webhook id is stored ...
        $this->assertSame('hook_existing', $gym->fresh()->mollie_config['webhook_id']);

        // ... and no webhook was created.
        Http::assertNotSent(fn (ClientRequest $request) => $request->method() === 'POST'
            && str_contains($request->url(), '/v2/webhooks'));
    }

    #[Test]
    public function it_creates_a_webhook_when_none_exists_for_the_url(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        Http::fake([
            'api.mollie.com/v2/webhooks*' => Http::sequence()
                ->push(['_embedded' => ['webhooks' => []]])
                ->push(['id' => 'hook_new']),
        ]);

        $this->actingAs($owner)
            ->postJson(route('v1.mollie.save-config'), $this->saveConfigPayload())
            ->assertOk();

        $this->assertSame('hook_new', $gym->fresh()->mollie_config['webhook_id']);

        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'POST'
            && str_contains($request->url(), '/v2/webhooks'));
    }

    #[Test]
    public function it_keeps_a_shared_webhook_when_another_gym_still_uses_it(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        // A second gym referencing the very same webhook
        $otherOwner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        Gym::factory()->create(['owner_id' => $otherOwner->id])->update([
            'mollie_config' => [
                'api_key' => 'live_'.str_repeat('c', 30),
                'oauth_token' => self::OAUTH_TOKEN,
                'enabled_methods' => ['creditcard'],
                'webhook_id' => 'hook_shared',
                'test_mode' => false,
            ],
        ]);

        $gym->update([
            'mollie_config' => [
                'api_key' => 'test_'.str_repeat('b', 30),
                'oauth_token' => self::OAUTH_TOKEN,
                'enabled_methods' => ['creditcard'],
                'webhook_id' => 'hook_shared',
                'test_mode' => true,
            ],
        ]);

        Http::fake();

        MollieService::deleteWebhookIfAny($gym->fresh()->mollie_config, $gym->id);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_deletes_a_webhook_that_no_other_gym_references(): void
    {
        [, $gym] = $this->ownerWithGym();

        $gym->update([
            'mollie_config' => [
                'api_key' => 'test_'.str_repeat('b', 30),
                'oauth_token' => self::OAUTH_TOKEN,
                'enabled_methods' => ['creditcard'],
                'webhook_id' => 'hook_solo',
                'test_mode' => true,
            ],
        ]);

        Http::fake();

        MollieService::deleteWebhookIfAny($gym->fresh()->mollie_config, $gym->id);

        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'DELETE'
            && str_contains($request->url(), '/v2/webhooks/hook_solo'));
    }
}
