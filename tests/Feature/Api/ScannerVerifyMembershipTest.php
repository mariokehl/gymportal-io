<?php

namespace Tests\Feature\Api;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\ScannerAccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers GET /api/scanner/verify-membership for plain access devices — the
 * only decision endpoint the scanner client calls (see gymportal-qr-scanner,
 * devices/saas.py::verify_membership). The dispenser/add-on branch is covered
 * by ScannerDispenserAccessTest.
 */
class ScannerVerifyMembershipTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private GymScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->scanner = GymScanner::create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Eingang',
            'device_task' => GymScanner::TASK_CHECKIN,
        ]);
    }

    private function member(array $attributes = []): Member
    {
        return Member::factory()->create(array_merge([
            'gym_id' => $this->gym->id,
            'status' => 'active',
        ], $attributes));
    }

    private function membershipFor(Member $member, array $attributes = []): Membership
    {
        $plan = MembershipPlan::factory()->create(['gym_id' => $this->gym->id]);

        return Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ], $attributes));
    }

    private function verify(array $params, ?GymScanner $scanner = null)
    {
        $scanner ??= $this->scanner;

        return $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query($params));
    }

    private function scanQr(Member $member, ?GymScanner $scanner = null)
    {
        return $this->verify([
            'scan_type' => 'qr_code',
            'member_id' => $member->id,
        ], $scanner);
    }

    private function scanNfc(string $cardId)
    {
        return $this->verify([
            'scan_type' => 'nfc_card',
            'nfc_card_id' => $cardId,
        ]);
    }

    /*
    | Authentication
    */

    #[Test]
    public function a_scan_without_a_token_is_rejected(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $this->getJson('/api/scanner/verify-membership?'.http_build_query([
            'scan_type' => 'qr_code',
            'member_id' => $member->id,
        ]))->assertStatus(401);
    }

    /*
    | Request validation
    */

    #[Test]
    public function a_missing_scan_type_is_a_bad_request(): void
    {
        $this->verify(['member_id' => '123'])->assertStatus(400);
    }

    #[Test]
    public function an_unknown_scan_type_is_a_bad_request(): void
    {
        $this->verify(['scan_type' => 'face_id', 'member_id' => '123'])
            ->assertStatus(400);
    }

    #[Test]
    public function a_qr_scan_without_a_member_id_is_a_bad_request(): void
    {
        $this->verify(['scan_type' => 'qr_code'])->assertStatus(400);
    }

    #[Test]
    public function an_nfc_scan_without_a_card_id_is_a_bad_request(): void
    {
        $this->verify(['scan_type' => 'nfc_card'])->assertStatus(400);
    }

    /*
    | QR code
    */

    #[Test]
    public function an_active_member_with_a_running_membership_is_let_in(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);

        $this->scanQr($member)
            ->assertOk()
            ->assertJsonPath('access_allowed', true)
            ->assertJsonPath('active', true)
            ->assertJsonPath('member_id', $member->id)
            ->assertJsonPath('scan_type', 'qr_code')
            ->assertJsonPath('message', 'Zugang gewährt')
            ->assertJsonPath('membership_expires', $membership->end_date?->toJSON());
    }

    #[Test]
    public function a_rolling_qr_code_is_treated_like_a_static_one(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $this->verify(['scan_type' => 'rolling_qr', 'member_id' => $member->id])
            ->assertOk()
            ->assertJsonPath('access_allowed', true)
            ->assertJsonPath('scan_type', 'rolling_qr');
    }

    #[Test]
    public function a_rolling_qr_scan_is_logged_as_a_qr_check_in(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $this->verify(['scan_type' => 'rolling_qr', 'member_id' => $member->id])
            ->assertOk();

        $this->assertSame(
            'qr_code',
            CheckIn::where('member_id', $member->id)->value('check_in_method')
        );
    }

    #[Test]
    public function an_unknown_member_id_is_not_found(): void
    {
        $this->verify(['scan_type' => 'qr_code', 'member_id' => '999999'])
            ->assertStatus(404);
    }

    #[Test]
    public function an_inactive_member_is_denied(): void
    {
        $member = $this->member(['status' => 'paused']);
        $this->membershipFor($member);

        $this->scanQr($member)->assertStatus(403);
    }

    #[Test]
    public function a_member_without_an_active_membership_is_denied(): void
    {
        $this->scanQr($this->member())->assertStatus(403);
    }

    #[Test]
    public function a_membership_starting_in_the_future_is_denied(): void
    {
        $member = $this->member();
        $this->membershipFor($member, [
            'start_date' => now()->addWeek()->toDateString(),
        ]);

        $this->scanQr($member)->assertStatus(403);
    }

    #[Test]
    public function a_membership_starting_today_is_accepted(): void
    {
        $member = $this->member();
        $this->membershipFor($member, ['start_date' => now()->toDateString()]);

        $this->scanQr($member)
            ->assertOk()
            ->assertJsonPath('access_allowed', true);
    }

    #[Test]
    public function a_member_of_another_gym_cannot_use_this_scanner(): void
    {
        $foreignMember = $this->foreignMember();

        // The ID exists, but not for this gym — the device must not open.
        $this->scanQr($foreignMember)->assertStatus(404);

        $this->assertSame(0, CheckIn::where('member_id', $foreignMember->id)->count());
    }

    #[Test]
    public function an_nfc_card_of_another_gym_cannot_use_this_scanner(): void
    {
        $foreignMember = $this->foreignMember();
        MemberAccessConfig::create([
            'member_id' => $foreignMember->id,
            'nfc_enabled' => true,
            'nfc_uid' => '04FFFFFF',
        ]);

        $this->scanNfc('04FFFFFF')->assertStatus(404);

        $this->assertSame(0, CheckIn::where('member_id', $foreignMember->id)->count());
    }

    /**
     * An active member with a running membership at a different gym.
     */
    private function foreignMember(): Member
    {
        $otherGym = Gym::factory()->create();
        $member = Member::factory()->create([
            'gym_id' => $otherGym->id,
            'status' => 'active',
        ]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $otherGym->id]);
        Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        return $member;
    }

    /*
    | NFC card
    */

    #[Test]
    public function a_registered_nfc_card_lets_the_member_in(): void
    {
        $member = $this->member();
        $this->membershipFor($member);
        MemberAccessConfig::create([
            'member_id' => $member->id,
            'nfc_enabled' => true,
            'nfc_uid' => '04A1B2C3',
        ]);

        $this->scanNfc('04A1B2C3')
            ->assertOk()
            ->assertJsonPath('access_allowed', true)
            ->assertJsonPath('member_id', $member->id)
            ->assertJsonPath('scan_type', 'nfc_card');
    }

    #[Test]
    public function an_unknown_nfc_card_is_not_found(): void
    {
        $this->scanNfc('DEADBEEF')->assertStatus(404);
    }

    #[Test]
    public function a_disabled_nfc_card_is_denied(): void
    {
        $member = $this->member();
        $this->membershipFor($member);
        MemberAccessConfig::create([
            'member_id' => $member->id,
            'nfc_enabled' => false,
            'nfc_uid' => '04A1B2C3',
        ]);

        $this->scanNfc('04A1B2C3')->assertStatus(403);
    }

    #[Test]
    public function an_nfc_scan_is_logged_as_an_nfc_check_in(): void
    {
        $member = $this->member();
        $this->membershipFor($member);
        MemberAccessConfig::create([
            'member_id' => $member->id,
            'nfc_enabled' => true,
            'nfc_uid' => '04A1B2C3',
        ]);

        $this->scanNfc('04A1B2C3')->assertOk();

        $this->assertSame(
            'nfc_card',
            CheckIn::where('member_id', $member->id)->value('check_in_method')
        );
    }

    /*
    | Guest access
    */

    #[Test]
    public function a_guest_gets_in_without_a_membership(): void
    {
        $guest = $this->member(['guest_access' => true]);

        $this->scanQr($guest)
            ->assertOk()
            ->assertJsonPath('access_allowed', true)
            ->assertJsonPath('membership_expires', null)
            ->assertJsonPath('message', 'Zugang gewährt (Gastzugang)');
    }

    #[Test]
    public function a_guest_scan_creates_a_check_in(): void
    {
        $guest = $this->member(['guest_access' => true]);

        $this->scanQr($guest)->assertOk();

        $this->assertSame(1, CheckIn::where('member_id', $guest->id)->count());
    }

    /*
    | Check-in behaviour
    */

    #[Test]
    public function a_successful_scan_creates_a_check_in(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $this->scanQr($member)->assertOk();

        $checkIn = CheckIn::where('member_id', $member->id)->first();

        $this->assertNotNull($checkIn);
        $this->assertSame($this->gym->id, $checkIn->gym_id);
        $this->assertSame('qr_code', $checkIn->check_in_method);
    }

    #[Test]
    public function a_denied_scan_creates_no_check_in(): void
    {
        $member = $this->member();

        $this->scanQr($member)->assertStatus(403);

        $this->assertSame(0, CheckIn::where('member_id', $member->id)->count());
    }

    #[Test]
    public function a_second_scan_within_30_seconds_is_waved_through(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $this->scanQr($member)->assertOk();

        $this->scanQr($member)
            ->assertOk()
            ->assertJsonPath('access_allowed', true)
            ->assertJsonPath('message', 'Zugang bereits gewährt');

        // The shortcut must not double-count the visit.
        $this->assertSame(1, CheckIn::where('member_id', $member->id)->count());
    }

    #[Test]
    public function a_scan_after_the_30_second_window_is_a_new_check_in(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $this->scanQr($member)->assertOk();

        $this->travel(31)->seconds();
        $this->scanQr($member)
            ->assertOk()
            ->assertJsonPath('message', 'Zugang gewährt');

        $this->assertSame(2, CheckIn::where('member_id', $member->id)->count());
    }

    /*
    | Access log
    */

    #[Test]
    public function a_granted_scan_is_written_to_the_access_log(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $this->scanQr($member)->assertOk();

        $log = ScannerAccessLog::where('member_id', $member->id)->first();

        $this->assertNotNull($log);
        $this->assertTrue((bool) $log->access_granted);
        $this->assertNull($log->denial_reason);
        $this->assertSame($this->gym->id, $log->gym_id);
        $this->assertSame($this->scanner->device_number, $log->device_number);
        $this->assertSame($this->scanner->id, $log->metadata['scanner_id']);
    }

    #[Test]
    public function a_denied_scan_is_logged_with_its_reason(): void
    {
        $member = $this->member();

        $this->scanQr($member)->assertStatus(403);

        $log = ScannerAccessLog::where('member_id', $member->id)->first();

        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->access_granted);
        $this->assertSame('Keine aktive Mitgliedschaft', $log->denial_reason);
    }

    #[Test]
    public function an_inactive_member_is_logged_with_their_status(): void
    {
        $member = $this->member(['status' => 'paused']);
        $this->membershipFor($member);

        $this->scanQr($member)->assertStatus(403);

        $this->assertSame(
            'Mitglied ist nicht aktiv (Status: paused)',
            ScannerAccessLog::where('member_id', $member->id)->value('denial_reason')
        );
    }

    #[Test]
    public function an_unknown_nfc_card_keeps_its_id_in_the_log(): void
    {
        $this->scanNfc('DEADBEEF')->assertStatus(404);

        $log = ScannerAccessLog::latest('id')->first();

        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->access_granted);
        $this->assertSame('NFC-ID nicht erkannt', $log->denial_reason);
        $this->assertSame('DEADBEEF', $log->metadata['nfc_card_id']);
    }

    #[Test]
    public function a_granted_nfc_scan_does_not_store_the_card_id(): void
    {
        $member = $this->member();
        $this->membershipFor($member);
        MemberAccessConfig::create([
            'member_id' => $member->id,
            'nfc_enabled' => true,
            'nfc_uid' => '04A1B2C3',
        ]);

        $this->scanNfc('04A1B2C3')->assertOk();

        $this->assertArrayNotHasKey(
            'nfc_card_id',
            ScannerAccessLog::where('member_id', $member->id)->value('metadata')
        );
    }
}
