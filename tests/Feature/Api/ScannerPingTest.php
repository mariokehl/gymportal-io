<?php

namespace Tests\Feature\Api;

use App\Models\Gym;
use App\Models\GymScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers GET /api/scanner/ping — the heartbeat the scanner client sends for
 * every configured device (see gymportal-qr-scanner, devices/saas.py::ping).
 */
class ScannerPingTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
    }

    private function scanner(array $attributes = []): GymScanner
    {
        return GymScanner::create(array_merge([
            'gym_id' => $this->gym->id,
            'device_name' => 'Eingang',
            'device_task' => GymScanner::TASK_CHECKIN,
        ], $attributes));
    }

    private function ping(GymScanner $scanner)
    {
        return $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/ping');
    }

    #[Test]
    public function a_known_scanner_is_answered_with_ok(): void
    {
        $this->ping($this->scanner())
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    #[Test]
    public function the_ping_writes_a_heartbeat_to_the_cache(): void
    {
        $scanner = $this->scanner();

        $this->ping($scanner)->assertOk();

        $this->assertNotNull(Cache::get("scanner_heartbeat:{$scanner->id}"));
    }

    #[Test]
    public function the_heartbeat_survives_a_single_missed_ping(): void
    {
        $scanner = $this->scanner();

        $this->ping($scanner)->assertOk();

        // The client pings every 12 minutes; the entry must outlive one gap.
        $this->travel(20)->minutes();

        $this->assertNotNull(Cache::get("scanner_heartbeat:{$scanner->id}"));
    }

    #[Test]
    public function the_heartbeat_expires_once_the_scanner_stays_silent(): void
    {
        $scanner = $this->scanner();

        $this->ping($scanner)->assertOk();

        $this->travel(26)->minutes();

        $this->assertNull(Cache::get("scanner_heartbeat:{$scanner->id}"));
    }

    #[Test]
    public function the_controller_throttles_its_own_database_write(): void
    {
        $scanner = $this->scanner();

        $this->ping($scanner)->assertOk();
        $this->assertTrue(Cache::has("scanner_db_throttle:{$scanner->id}"));

        $this->travel(30)->minutes();
        $this->ping($scanner)->assertOk();
        $this->assertTrue(Cache::has("scanner_db_throttle:{$scanner->id}"));

        $this->travel(31)->minutes();
        $this->assertFalse(Cache::has("scanner_db_throttle:{$scanner->id}"));
    }

    #[Test]
    public function last_seen_at_is_refreshed_on_every_ping(): void
    {
        $scanner = $this->scanner();

        $this->ping($scanner)->assertOk();
        $firstSeenAt = $scanner->fresh()->last_seen_at;

        $this->travel(30)->minutes();
        $this->ping($scanner)->assertOk();

        // The controller throttles its own touch() to once per hour, but
        // AuthenticateScanner touches the scanner on every request — so
        // last_seen_at advances regardless of the throttle.
        $this->assertTrue($scanner->fresh()->last_seen_at->greaterThan($firstSeenAt));
    }

    #[Test]
    public function a_request_without_a_token_is_rejected(): void
    {
        $this->getJson('/api/scanner/ping')->assertStatus(401);
    }

    #[Test]
    public function an_unknown_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer scan_does_not_exist')
            ->getJson('/api/scanner/ping')
            ->assertStatus(401);
    }

    #[Test]
    public function an_inactive_scanner_is_rejected(): void
    {
        $this->ping($this->scanner(['is_active' => false]))->assertStatus(401);
    }

    #[Test]
    public function a_scanner_with_an_expired_token_is_rejected(): void
    {
        $this->ping($this->scanner(['token_expires_at' => now()->subDay()]))
            ->assertStatus(401);
    }

    #[Test]
    public function a_scanner_outside_its_ip_whitelist_is_rejected(): void
    {
        $this->ping($this->scanner(['allowed_ips' => ['10.0.0.1']]))
            ->assertStatus(401);
    }

    #[Test]
    public function the_token_is_also_accepted_from_the_custom_header(): void
    {
        $scanner = $this->scanner();

        $this->withHeader('X-Scanner-Token', $scanner->getPlainTextToken())
            ->getJson('/api/scanner/ping')
            ->assertOk();
    }

    #[Test]
    public function the_token_is_also_accepted_as_a_query_parameter(): void
    {
        $scanner = $this->scanner();

        $this->getJson('/api/scanner/ping?api_token='.$scanner->getPlainTextToken())
            ->assertOk();
    }

    #[Test]
    public function a_locked_scanner_is_rejected(): void
    {
        $scanner = $this->scanner();
        // locked_until is guarded, so it is set outside of the fillable path.
        $scanner->forceFill(['locked_until' => now()->addMinutes(15)])->save();

        $this->ping($scanner)->assertStatus(401);
    }

    #[Test]
    public function repeated_failures_lock_the_scanner_out(): void
    {
        $scanner = $this->scanner(['is_active' => false]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->ping($scanner)->assertStatus(401);
        }

        $this->assertNotNull($scanner->fresh()->locked_until);
    }

    #[Test]
    public function the_lock_is_lifted_once_it_expires(): void
    {
        $scanner = $this->scanner();
        $scanner->forceFill([
            'failed_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ])->save();

        // Keep the original instance: it carries the plaintext token, which
        // fresh() cannot reload — only its hash is stored.
        $this->ping($scanner)->assertStatus(401);

        $this->travel(16)->minutes();
        $this->ping($scanner)->assertOk();

        // The successful request has to clear the counter, otherwise the next
        // single failure would lock the scanner out again right away.
        $this->assertSame(0, $scanner->fresh()->failed_attempts);
        $this->assertNull($scanner->fresh()->locked_until);
    }

    #[Test]
    public function a_successful_ping_clears_earlier_failed_attempts(): void
    {
        $scanner = $this->scanner();
        $scanner->registerFailedAttempt();
        $this->assertSame(1, $scanner->fresh()->failed_attempts);

        $this->ping($scanner)->assertOk();

        $this->assertSame(0, $scanner->fresh()->failed_attempts);
    }

    /*
    | Brute-force protection
    |
    | The device lockout above only ever sees known tokens. These cover the
    | actual attack: guessing tokens that match no device at all.
    */

    #[Test]
    public function guessing_unknown_tokens_blocks_the_ip(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withHeader('Authorization', 'Bearer scan_guess'.$attempt)
                ->getJson('/api/scanner/ping')
                ->assertStatus(401);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts('scanner-auth:127.0.0.1', 10));
    }

    #[Test]
    public function a_blocked_ip_cannot_use_a_valid_token_either(): void
    {
        $scanner = $this->scanner();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withHeader('Authorization', 'Bearer scan_guess'.$attempt)
                ->getJson('/api/scanner/ping');
        }

        // The guessing spree locks out the legitimate device on that IP too.
        // Accepted trade-off: a scanner has its own line, an attacker does not.
        $this->ping($scanner)->assertStatus(401);
    }

    #[Test]
    public function the_ip_block_expires(): void
    {
        $scanner = $this->scanner();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withHeader('Authorization', 'Bearer scan_guess'.$attempt)
                ->getJson('/api/scanner/ping');
        }

        $this->ping($scanner)->assertStatus(401);

        $this->travel(16)->minutes();

        $this->ping($scanner)->assertOk();
    }

    #[Test]
    public function a_successful_ping_clears_the_ip_counter(): void
    {
        $scanner = $this->scanner();

        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->withHeader('Authorization', 'Bearer scan_guess'.$attempt)
                ->getJson('/api/scanner/ping');
        }

        $this->ping($scanner)->assertOk();

        // Counter reset, so the next failure starts from zero rather than
        // tipping the device over the limit.
        $this->assertSame(0, RateLimiter::attempts('scanner-auth:127.0.0.1'));
    }

    #[Test]
    public function every_rejection_returns_the_same_message(): void
    {
        $unknown = $this->withHeader('Authorization', 'Bearer scan_nope')
            ->get('/api/scanner/ping');

        $inactive = $this->ping($this->scanner(['is_active' => false]));
        $expired = $this->ping($this->scanner(['token_expires_at' => now()->subDay()]));
        $foreignIp = $this->ping($this->scanner(['allowed_ips' => ['10.0.0.1']]));
        $missing = $this->get('/api/scanner/ping');

        // Nothing in the body may reveal whether the token exists.
        $expected = "code=9999\nerror=Authentication failed";

        foreach ([$unknown, $inactive, $expired, $foreignIp, $missing] as $response) {
            $this->assertSame($expected, $response->getContent());
        }
    }

    #[Test]
    public function the_rejection_never_echoes_the_client_ip(): void
    {
        $response = $this->ping($this->scanner(['allowed_ips' => ['10.0.0.1']]));

        $this->assertStringNotContainsString('127.0.0.1', $response->getContent());
    }

    #[Test]
    public function the_log_records_the_real_reason_without_the_token(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $this->assertSame('Scanner authentication failed', $message);
                $this->assertSame('Invalid API token', $context['reason']);
                $this->assertArrayNotHasKey('token', $context);

                return true;
            });

        $this->withHeader('Authorization', 'Bearer scan_secret_guess')
            ->getJson('/api/scanner/ping')
            ->assertStatus(401);
    }
}
