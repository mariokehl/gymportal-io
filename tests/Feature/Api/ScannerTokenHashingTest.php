<?php

namespace Tests\Feature\Api;

use App\Models\Gym;
use App\Models\GymScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the hashed storage of scanner API tokens. The plaintext is handed out
 * exactly once, at creation; the database only ever keeps its SHA-256 hash.
 */
class ScannerTokenHashingTest extends TestCase
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

    /**
     * A device as it existed before tokens were hashed.
     */
    private function legacyScanner(string $token): GymScanner
    {
        $scanner = $this->scanner();
        $scanner->forceFill(['api_token' => $token, 'api_token_hash' => null])->save();

        return $scanner->fresh();
    }

    #[Test]
    public function a_new_device_stores_only_the_hash(): void
    {
        $scanner = $this->scanner();
        $plain = $scanner->getPlainTextToken();

        $this->assertNotNull($plain);
        $this->assertNull($scanner->fresh()->api_token);
        $this->assertSame(hash('sha256', $plain), $scanner->fresh()->api_token_hash);
    }

    #[Test]
    public function the_plaintext_is_not_recoverable_from_the_database(): void
    {
        $scanner = $this->scanner();

        // Reloading drops the plaintext — it lives only on the instance that
        // generated it, for the length of that request.
        $this->assertNull($scanner->fresh()->getPlainTextToken());
    }

    #[Test]
    public function a_hashed_token_authenticates(): void
    {
        $scanner = $this->scanner();

        $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/ping')
            ->assertOk();
    }

    #[Test]
    public function two_hash_only_devices_can_coexist(): void
    {
        // The plaintext column is UNIQUE and now holds NULL for both.
        $first = $this->scanner(['device_name' => 'Eingang']);
        $second = $this->scanner(['device_name' => 'Ausgang']);

        $this->assertNotSame($first->id, $second->id);
        $this->assertNull($first->fresh()->api_token);
        $this->assertNull($second->fresh()->api_token);
    }

    #[Test]
    public function the_token_never_leaves_through_serialisation(): void
    {
        $serialised = $this->scanner()->fresh()->toArray();

        $this->assertArrayNotHasKey('api_token', $serialised);
        $this->assertArrayNotHasKey('api_token_hash', $serialised);
    }

    #[Test]
    public function regenerating_issues_a_working_token_and_kills_the_old_one(): void
    {
        $scanner = $this->scanner();
        $old = $scanner->getPlainTextToken();

        $new = $scanner->regenerateToken();

        $this->withHeader('Authorization', 'Bearer '.$new)
            ->getJson('/api/scanner/ping')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$old)
            ->getJson('/api/scanner/ping')
            ->assertStatus(401);
    }

    /*
    | Dual-run window: devices provisioned before the hash column existed
    */

    #[Test]
    public function a_legacy_plaintext_token_still_authenticates(): void
    {
        $this->legacyScanner('scan_legacy_token_value');

        $this->withHeader('Authorization', 'Bearer scan_legacy_token_value')
            ->getJson('/api/scanner/ping')
            ->assertOk();
    }

    #[Test]
    public function regenerating_migrates_a_legacy_device_to_hash_only(): void
    {
        $scanner = $this->legacyScanner('scan_legacy_token_value');

        $scanner->regenerateToken();

        $this->assertNull($scanner->fresh()->api_token);
        $this->withHeader('Authorization', 'Bearer scan_legacy_token_value')
            ->getJson('/api/scanner/ping')
            ->assertStatus(401);
    }

    /*
    | Config download availability
    */

    #[Test]
    public function a_hash_only_device_reports_its_config_as_unavailable(): void
    {
        $this->assertFalse($this->scanner()->fresh()->config_downloadable);
    }

    #[Test]
    public function a_legacy_device_can_still_offer_its_config(): void
    {
        $this->assertTrue($this->legacyScanner('scan_legacy_token_value')->config_downloadable);
    }

    #[Test]
    public function the_flag_is_exposed_to_the_frontend(): void
    {
        $this->assertArrayHasKey('config_downloadable', $this->scanner()->fresh()->toArray());
    }
}
