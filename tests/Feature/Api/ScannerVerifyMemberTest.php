<?php

namespace Tests\Feature\Api;

use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers GET /api/scanner/verify-member.
 *
 * The endpoint answers one question only: is this code genuine? It says
 * nothing about whether the member may enter — that stays with
 * verify-membership. The point of the route is that a device can verify a code
 * signed by a *sibling* location, which it cannot do on its own because it only
 * holds its own SECRET_KEY.
 */
class ScannerVerifyMemberTest extends TestCase
{
    use RefreshDatabase;

    private Gym $berlin;

    private Gym $hamburg;

    private GymScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        // One organisation: both locations share an owner.
        $owner = User::factory()->create();
        $this->berlin = Gym::factory()->create(['owner_id' => $owner->id, 'name' => 'FitZone Berlin']);
        $this->hamburg = Gym::factory()->create(['owner_id' => $owner->id, 'name' => 'FitZone Hamburg']);

        $this->berlin->generateScannerSecretKey();
        $this->hamburg->generateScannerSecretKey();

        $this->scanner = GymScanner::create([
            'gym_id' => $this->berlin->id,
            'device_name' => 'Eingang Berlin',
            'device_task' => GymScanner::TASK_CHECKIN,
        ]);
    }

    private function verify(array $query): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->scanner->getPlainTextToken())
            ->getJson('/api/scanner/verify-member?'.http_build_query($query));
    }

    private function staticHash(Member $member, string $timestamp, ?Gym $signedBy = null): string
    {
        return hash_hmac(
            'sha256',
            $member->id.':'.$timestamp,
            ($signedBy ?? $member->gym)->getCurrentScannerKey()
        );
    }

    #[Test]
    public function it_accepts_a_code_signed_by_the_scanners_own_location(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);
        $timestamp = now()->toIso8601ZuluString();

        $this->verify([
            'member_id' => $member->id,
            'timestamp' => $timestamp,
            'hash' => $this->staticHash($member, $timestamp),
        ])->assertOk()->assertExactJson(['valid' => true]);
    }

    /**
     * The reason this route exists: Hamburg's key is unknown to the Berlin
     * device, so only the server can tell that this code is genuine.
     */
    #[Test]
    public function it_accepts_a_code_signed_by_a_sibling_location(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->hamburg->id]);
        $timestamp = now()->toIso8601ZuluString();

        $this->verify([
            'member_id' => $member->id,
            'timestamp' => $timestamp,
            'hash' => $this->staticHash($member, $timestamp),
        ])->assertOk()->assertExactJson(['valid' => true]);
    }

    #[Test]
    public function it_rejects_a_hash_signed_with_the_wrong_locations_key(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->hamburg->id]);
        $timestamp = now()->toIso8601ZuluString();

        // Genuine member, but signed with Berlin's key instead of Hamburg's.
        $this->verify([
            'member_id' => $member->id,
            'timestamp' => $timestamp,
            'hash' => $this->staticHash($member, $timestamp, $this->berlin),
        ])->assertOk()->assertExactJson(['valid' => false]);
    }

    #[Test]
    public function it_rejects_a_forged_hash(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);

        $this->verify([
            'member_id' => $member->id,
            'timestamp' => now()->toIso8601ZuluString(),
            'hash' => str_repeat('a', 64),
        ])->assertOk()->assertExactJson(['valid' => false]);
    }

    /**
     * A member of an unrelated studio must read as invalid, not as "found but
     * wrong hash" — the device must never learn that the ID exists at all.
     */
    #[Test]
    public function it_rejects_a_member_outside_the_organisation(): void
    {
        $foreignGym = Gym::factory()->create(['owner_id' => User::factory()->create()->id]);
        $foreignGym->generateScannerSecretKey();

        $member = Member::factory()->create(['gym_id' => $foreignGym->id]);
        $timestamp = now()->toIso8601ZuluString();

        $this->verify([
            'member_id' => $member->id,
            'timestamp' => $timestamp,
            'hash' => $this->staticHash($member, $timestamp),
        ])->assertOk()->assertExactJson(['valid' => false]);
    }

    #[Test]
    public function it_rejects_an_unknown_member(): void
    {
        $this->verify([
            'member_id' => '99999999',
            'timestamp' => now()->toIso8601ZuluString(),
            'hash' => str_repeat('a', 64),
        ])->assertOk()->assertExactJson(['valid' => false]);
    }

    #[Test]
    public function it_rejects_an_expired_static_code(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);
        $timestamp = now()->subMinutes(31)->toIso8601ZuluString();

        // The signature itself is genuine — only the age makes it invalid.
        $this->verify([
            'member_id' => $member->id,
            'timestamp' => $timestamp,
            'hash' => $this->staticHash($member, $timestamp),
        ])->assertOk()->assertExactJson(['valid' => false]);
    }

    #[Test]
    public function it_rejects_a_code_dated_far_in_the_future(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);
        $timestamp = now()->addMinutes(30)->toIso8601ZuluString();

        $this->verify([
            'member_id' => $member->id,
            'timestamp' => $timestamp,
            'hash' => $this->staticHash($member, $timestamp),
        ])->assertOk()->assertExactJson(['valid' => false]);
    }

    #[Test]
    public function it_rejects_an_unparsable_timestamp(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);

        $this->verify([
            'member_id' => $member->id,
            'timestamp' => 'not-a-date',
            'hash' => $this->staticHash($member, 'not-a-date'),
        ])->assertOk()->assertExactJson(['valid' => false]);
    }

    /**
     * A rolling code carries no timestamp; it is bound to a TOTP time step.
     */
    #[Test]
    public function it_accepts_a_rolling_code_of_a_sibling_location(): void
    {
        $this->hamburg->update(['rolling_qr_enabled' => true, 'rolling_qr_interval' => 3]);
        $member = Member::factory()->create(['gym_id' => $this->hamburg->id]);

        $timeStep = (int) floor(time() / 3);
        $hash = hash_hmac(
            'sha256',
            $member->id.':'.$timeStep,
            $this->hamburg->getCurrentScannerKey()
        );

        $this->verify([
            'member_id' => $member->id,
            'hash' => $hash,
        ])->assertOk()->assertExactJson(['valid' => true]);
    }

    #[Test]
    public function it_rejects_a_rolling_code_from_outside_the_tolerance_window(): void
    {
        $this->hamburg->update([
            'rolling_qr_enabled' => true,
            'rolling_qr_interval' => 3,
            'rolling_qr_tolerance_windows' => 1,
        ]);
        $member = Member::factory()->create(['gym_id' => $this->hamburg->id]);

        // Five steps back is well outside the tolerated window.
        $timeStep = (int) floor(time() / 3) - 5;
        $hash = hash_hmac(
            'sha256',
            $member->id.':'.$timeStep,
            $this->hamburg->getCurrentScannerKey()
        );

        $this->verify([
            'member_id' => $member->id,
            'hash' => $hash,
        ])->assertOk()->assertExactJson(['valid' => false]);
    }

    /**
     * Authenticity only: the endpoint does not consume the code. Replay
     * protection lives on the device (validators.py), which registers a
     * confirmed code against its own guard.
     */
    #[Test]
    public function it_does_not_consume_a_rolling_code(): void
    {
        $this->hamburg->update(['rolling_qr_enabled' => true, 'rolling_qr_interval' => 3]);
        $member = Member::factory()->create(['gym_id' => $this->hamburg->id]);

        $hash = hash_hmac(
            'sha256',
            $member->id.':'.((int) floor(time() / 3)),
            $this->hamburg->getCurrentScannerKey()
        );

        $query = ['member_id' => $member->id, 'hash' => $hash];

        $this->verify($query)->assertOk()->assertExactJson(['valid' => true]);
        $this->verify($query)->assertOk()->assertExactJson(['valid' => true]);
    }

    /**
     * A static code is not single-use: it stays valid for its whole window and
     * a member may legitimately scan it twice (e.g. entrance, then dispenser).
     */
    #[Test]
    public function it_accepts_the_same_static_code_twice(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);
        $timestamp = now()->toIso8601ZuluString();

        $query = [
            'member_id' => $member->id,
            'timestamp' => $timestamp,
            'hash' => $this->staticHash($member, $timestamp),
        ];

        $this->verify($query)->assertOk()->assertExactJson(['valid' => true]);
        $this->verify($query)->assertOk()->assertExactJson(['valid' => true]);
    }

    #[Test]
    public function it_rejects_a_request_without_a_hash(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);

        $this->verify([
            'member_id' => $member->id,
            'timestamp' => now()->toIso8601ZuluString(),
        ])->assertOk()->assertExactJson(['valid' => false]);
    }

    #[Test]
    public function it_requires_scanner_authentication(): void
    {
        $this->getJson('/api/scanner/verify-member?member_id=1&hash=abc')
            ->assertStatus(401);
    }
}
