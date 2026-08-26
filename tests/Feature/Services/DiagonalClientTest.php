<?php

namespace Tests\Feature\Services;

use App\Models\Gym;
use App\Services\Diagonal\DiagonalApiException;
use App\Services\Diagonal\DiagonalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiagonalClientTest extends TestCase
{
    use RefreshDatabase;

    private DiagonalClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = app(DiagonalClient::class);
        Cache::flush();
    }

    private function gym(array $settings = []): Gym
    {
        $gym = Gym::factory()->create();
        $gym->update([
            'inkasso_settings' => array_merge($gym->inkasso_settings, array_merge([
                'active' => true,
                'tenant_id' => '40218-BER',
                'client_number' => '40218',
                'username' => 'fitzone-berlin@api',
                'password' => Crypt::encryptString('geheimespasswort'),
            ], $settings)),
        ]);

        return $gym->fresh();
    }

    public function test_login_returns_the_token(): void
    {
        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token', 'expiration' => '2026-12-31'], 200),
        ]);

        $this->assertSame('jwt-token', $this->client->login('user', 'pass'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/v1.2/Authenticate/login')
            && $request['username'] === 'user'
            && $request['password'] === 'pass');
    }

    public function test_sandbox_mode_targets_the_test_host(): void
    {
        config([
            'services.diagonal.base_url' => 'https://api.diagonal-service.de',
            'services.diagonal.sandbox_base_url' => 'https://api.dev.diagonal-service.de',
        ]);

        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200),
            '*/FileData/AddItem/*' => Http::response(['data' => ['guid' => 'guid-1']], 200),
        ]);

        $this->client->addFile($this->gym(['sandbox' => true]), ['foo' => 'bar']);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.dev.diagonal-service.de'));
    }

    public function test_production_mode_targets_the_live_host(): void
    {
        config([
            'services.diagonal.base_url' => 'https://api.diagonal-service.de',
            'services.diagonal.sandbox_base_url' => 'https://api.dev.diagonal-service.de',
        ]);

        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200),
            '*/FileData/AddItem/*' => Http::response(['data' => ['guid' => 'guid-1']], 200),
        ]);

        $this->client->addFile($this->gym(), ['foo' => 'bar']);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.diagonal-service.de'));
    }

    public function test_tokens_are_cached_separately_per_environment(): void
    {
        Http::fake(['*/Authenticate/login' => Http::response(['token' => 'sandbox-token'], 200)]);

        $gym = $this->gym(['sandbox' => true]);

        $this->assertSame('sandbox-token', $this->client->tokenFor($gym));
        $this->assertSame('sandbox-token', Cache::get("diagonal.token.{$gym->id}.sandbox"));
        $this->assertNull(Cache::get("diagonal.token.{$gym->id}"));
    }

    public function test_forgetting_the_token_clears_both_environments(): void
    {
        $gym = $this->gym();

        Cache::put("diagonal.token.{$gym->id}", 'live', now()->addMinutes(30));
        Cache::put("diagonal.token.{$gym->id}.sandbox", 'sandbox', now()->addMinutes(30));

        $this->client->forgetToken($gym);

        $this->assertNull(Cache::get("diagonal.token.{$gym->id}"));
        $this->assertNull(Cache::get("diagonal.token.{$gym->id}.sandbox"));
    }

    public function test_login_failure_raises_a_domain_exception(): void
    {
        Http::fake(['*/Authenticate/login' => Http::response(['message' => 'invalid'], 401)]);

        $this->expectException(DiagonalApiException::class);
        $this->expectExceptionMessage('Die DIAGONAL-Zugangsdaten wurden abgelehnt.');

        $this->client->login('user', 'wrong');
    }

    public function test_missing_token_in_response_is_rejected(): void
    {
        Http::fake(['*/Authenticate/login' => Http::response(['expiration' => '2026-12-31'], 200)]);

        $this->expectException(DiagonalApiException::class);

        $this->client->login('user', 'pass');
    }

    public function test_it_decrypts_the_stored_password_and_caches_the_token(): void
    {
        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'cached-token'], 200),
        ]);

        $gym = $this->gym();

        $this->assertSame('cached-token', $this->client->tokenFor($gym));
        $this->assertSame('cached-token', $this->client->tokenFor($gym));

        // The second call must be served from the cache.
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['password'] === 'geheimespasswort');
    }

    public function test_it_fails_without_credentials(): void
    {
        $gym = $this->gym(['username' => null, 'password' => null]);

        $this->expectException(DiagonalApiException::class);
        $this->expectExceptionMessage('Es sind keine DIAGONAL-Zugangsdaten hinterlegt.');

        $this->client->tokenFor($gym);
    }

    public function test_add_file_sends_the_bearer_token_and_returns_the_guid(): void
    {
        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200),
            '*/FileData/AddItem/*' => Http::response(['status' => 'ok', 'data' => ['guid' => 'abc-123']], 200),
        ]);

        $guid = $this->client->addFile($this->gym(), ['creditor' => ['clientNumber' => '40218']]);

        $this->assertSame('abc-123', $guid);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/FileData/AddItem/')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer jwt-token')
                && str_contains($request->url(), '40218-BER');
        });
    }

    public function test_get_file_state_reads_the_status(): void
    {
        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200),
            '*/FileData/GetStateByGuid/*' => Http::response(['status' => 'InProgress'], 200),
        ]);

        $this->assertSame('InProgress', $this->client->getFileState($this->gym(), 'abc-123'));
    }

    public function test_api_errors_are_translated(): void
    {
        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200),
            '*/FileData/AddItem/*' => Http::response(['message' => 'Ungültige Adresse', 'errorCode' => 'E42'], 422),
        ]);

        try {
            $this->client->addFile($this->gym(), []);
            $this->fail('Expected DiagonalApiException');
        } catch (DiagonalApiException $e) {
            $this->assertSame('Ungültige Adresse', $e->getMessage());
            $this->assertSame(422, $e->status);
            $this->assertSame('E42', $e->errorCode);
        }
    }

    public function test_it_requires_a_tenant_id(): void
    {
        Http::fake(['*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200)]);

        $this->expectException(DiagonalApiException::class);
        $this->expectExceptionMessage('Es ist keine Mandanten-ID hinterlegt.');

        $this->client->addFile($this->gym(['tenant_id' => null]), []);
    }

    public function test_connection_test_reports_success(): void
    {
        Http::fake(['*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200)]);

        $result = $this->client->testConnection($this->gym());

        $this->assertTrue($result['success']);
        $this->assertSame('Verbindung zu DIAGONAL erfolgreich hergestellt.', $result['message']);
    }

    public function test_connection_test_reports_rejected_credentials(): void
    {
        Http::fake(['*/Authenticate/login' => Http::response(['message' => 'nope'], 401)]);

        $result = $this->client->testConnection($this->gym());

        $this->assertFalse($result['success']);
        $this->assertSame('Die DIAGONAL-Zugangsdaten wurden abgelehnt.', $result['message']);
    }

    public function test_connection_test_always_performs_a_fresh_login(): void
    {
        $gym = $this->gym();
        Cache::put("diagonal.token.{$gym->id}", 'stale-token', now()->addHour());

        Http::fake(['*/Authenticate/login' => Http::response(['token' => 'fresh-token'], 200)]);

        $this->assertTrue($this->client->testConnection($gym)['success']);

        // The cached token must not be reused for the check ...
        Http::assertSentCount(1);
        // ... and the probe must not overwrite the cache either, because it may
        // run against credentials that are not saved yet.
        $this->assertSame('stale-token', Cache::get("diagonal.token.{$gym->id}"));
    }

    public function test_cancel_file_posts_to_the_cancellation_endpoint(): void
    {
        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200),
            '*/FileCancellation/AddItem/*' => Http::response(['data' => ['guid' => 'cancel-1']], 200),
        ]);

        $guid = $this->client->cancelFile($this->gym(), [
            'guid' => 'abc-123',
            'cancellationReason' => 'Goodwill',
            'effectDate' => '2026-08-12',
        ]);

        $this->assertSame('cancel-1', $guid);
    }
}
