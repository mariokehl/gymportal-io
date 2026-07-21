<?php

namespace Tests\Feature\Api\V1;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MollieWebhookLookupTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_URL = 'https://example.test/api/v1/public/mollie/webhook';

    private function owner(): User
    {
        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $owner = User::factory()->create(['role_id' => $roleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return $owner->fresh();
    }

    #[Test]
    public function it_reports_an_existing_webhook_for_the_given_url(): void
    {
        Http::fake([
            'api.mollie.com/v2/webhooks*' => Http::response([
                '_embedded' => [
                    'webhooks' => [
                        ['id' => 'hook_other', 'url' => 'https://other.test/hook', 'status' => 'enabled'],
                        ['id' => 'hook_1', 'url' => self::WEBHOOK_URL, 'status' => 'enabled', 'name' => 'Webhook #1'],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->owner())
            ->postJson(route('v1.mollie.lookup-webhook'), [
                'oauth_token' => 'access_'.str_repeat('a', 30),
                'webhook_url' => self::WEBHOOK_URL,
                'test_mode' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'exists' => true,
                'webhook' => ['id' => 'hook_1', 'status' => 'enabled'],
            ]);
    }

    #[Test]
    public function it_reports_no_webhook_when_the_url_is_not_registered(): void
    {
        Http::fake([
            'api.mollie.com/v2/webhooks*' => Http::response([
                '_embedded' => ['webhooks' => [['id' => 'hook_other', 'url' => 'https://other.test/hook']]],
            ]),
        ]);

        $this->actingAs($this->owner())
            ->postJson(route('v1.mollie.lookup-webhook'), [
                'oauth_token' => 'access_'.str_repeat('a', 30),
                'webhook_url' => self::WEBHOOK_URL,
                'test_mode' => false,
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'exists' => false]);
    }

    #[Test]
    public function it_fails_without_an_oauth_token(): void
    {
        Http::fake();

        $this->actingAs($this->owner())
            ->postJson(route('v1.mollie.lookup-webhook'), [
                'webhook_url' => self::WEBHOOK_URL,
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'exists' => false]);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->postJson(route('v1.mollie.lookup-webhook'), [
            'webhook_url' => self::WEBHOOK_URL,
        ])->assertUnauthorized();
    }
}
