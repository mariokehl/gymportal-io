<?php

namespace Tests\Feature\Api;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\Role;
use App\Models\User;
use App\Services\GuestQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The guest QR code is checked by the server: a rolling code in the app that
 * expires within a minute, and a revocable token in the wallet pass.
 */
class GuestQrCodeTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private GymScanner $entrance;

    private Member $guest;

    private GuestQrCodeService $codes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->codes = app(GuestQrCodeService::class);
        $this->gym = Gym::factory()->create();
        $this->entrance = $this->scanner('Eingang');

        $this->guest = Member::factory()->create(['gym_id' => $this->gym->id, 'status' => 'extern']);
        MemberAccessConfig::create([
            'member_id' => $this->guest->id,
            'qr_code_enabled' => true,
            'visit_card_enabled' => true,
            'visit_card_entries' => 10,
        ]);
    }

    private function scanner(string $name): GymScanner
    {
        return GymScanner::create([
            'gym_id' => $this->gym->id,
            'device_name' => $name,
            'device_task' => GymScanner::TASK_CHECKIN,
        ]);
    }

    private function scan(?string $guestQr, ?GymScanner $scanner = null)
    {
        $scanner ??= $this->entrance;

        return $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query(array_filter([
                'scan_type' => 'guest_service',
                'member_id' => $this->guest->id,
                'guest_qr' => $guestQr,
            ])));
    }

    private function entries(): int
    {
        return (int) $this->guest->accessConfig()->value('visit_card_entries');
    }

    #[Test]
    public function a_current_rolling_code_is_accepted(): void
    {
        $this->scan($this->codes->rollingCode($this->guest)['qr_code'])->assertOk();

        $this->assertSame(9, $this->entries());
    }

    #[Test]
    public function a_scan_without_the_code_is_rejected(): void
    {
        $this->scan(null)->assertStatus(400);

        $this->assertSame(10, $this->entries());
    }

    #[Test]
    public function a_rolling_code_expires(): void
    {
        $code = $this->codes->rollingCode($this->guest)['qr_code'];

        $this->travel((GuestQrCodeService::ROLLING_TOLERANCE_WINDOWS + 1) * GuestQrCodeService::ROLLING_INTERVAL_SECONDS + 1)->seconds();

        $this->scan($code)->assertForbidden();
        $this->assertSame(10, $this->entries());
    }

    #[Test]
    public function a_forged_or_foreign_code_is_rejected(): void
    {
        $other = Member::factory()->create(['gym_id' => $this->gym->id, 'status' => 'extern']);

        // A valid code of another guest, presented with this guest's id.
        $this->scan($this->codes->rollingCode($other)['qr_code'])->assertForbidden();

        $forged = json_encode(['type' => 'guest_service', 'variant' => 'rolling', 'mid' => (string) $this->guest->id, 'hash' => str_repeat('a', 64)]);
        $this->scan($forged)->assertForbidden();

        $this->assertSame(10, $this->entries());
    }

    #[Test]
    public function the_old_static_format_is_no_longer_accepted(): void
    {
        $legacy = json_encode([
            'type' => 'guest_service',
            'purpose' => 'service_credit',
            'mid' => (string) $this->guest->id,
            'ts' => now()->toIso8601String(),
            'hash' => hash_hmac('sha256', 'guest_service:'.$this->guest->id.':'.now()->toIso8601String(), (string) $this->gym->getCurrentScannerKey()),
        ]);

        $this->scan($legacy)->assertForbidden();
    }

    #[Test]
    public function a_rolling_code_used_at_one_device_is_a_replay_at_another(): void
    {
        $code = $this->codes->rollingCode($this->guest)['qr_code'];

        $this->scan($code)->assertOk();
        $this->scan($code, $this->scanner('Eingang 2'))->assertForbidden();
    }

    #[Test]
    public function the_same_device_may_repeat_a_code_without_using_another_entry(): void
    {
        $code = $this->codes->rollingCode($this->guest)['qr_code'];

        $this->scan($code)->assertOk();
        $this->scan($code)->assertOk();

        $this->assertSame(9, $this->entries());
        $this->assertSame(1, CheckIn::where('member_id', $this->guest->id)->count());
    }

    #[Test]
    public function a_wallet_pass_code_is_accepted_until_a_new_pass_replaces_it(): void
    {
        $first = $this->codes->issuePassCode($this->guest);
        $this->scan($first)->assertOk();

        $this->travel(1)->minute();
        $second = $this->codes->issuePassCode($this->guest);

        $this->scan($first)->assertForbidden();
        $this->scan($second)->assertOk();
    }

    #[Test]
    public function the_operator_can_revoke_the_wallet_pass(): void
    {
        $pass = $this->codes->issuePassCode($this->guest);

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym->update(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $this->gym->id]);

        $this->actingAs($owner->fresh())
            ->delete(route('members.access.guest-pass.destroy', $this->guest))
            ->assertRedirect();

        $this->assertFalse($this->guest->accessConfig->fresh()->has_guest_pass);
        $this->scan($pass)->assertForbidden();
    }

    #[Test]
    public function the_guest_app_gets_a_rolling_code_with_its_refresh_time(): void
    {
        $token = $this->guest->createToken('guest-pwa-full', ['guest-pwa', 'full'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/guests/qr-code')
            ->assertOk();

        $this->assertSame('rolling', json_decode($response->json('data.qr_code'), true)['variant']);
        $this->assertLessThanOrEqual(GuestQrCodeService::ROLLING_INTERVAL_SECONDS, $response->json('data.refresh_in'));
    }
}
