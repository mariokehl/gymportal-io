<?php

namespace Tests\Feature\Pwa;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\ScannerAccessLog;
use App\Models\User;
use App\Services\StationCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The printed check-in station: a static QR code at the counter that members
 * scan with their own phone, for gyms running without scanner hardware.
 */
class StationCheckInTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/pwa/member/checkin/toggle';

    /**
     * A gym with the station switched on, a member of it, and a session token.
     *
     * @return array{0: Gym, 1: Member, 2: string, 3: string} gym, member, bearer token, station token
     */
    private function stationSetup(array $gymOverrides = [], array $memberOverrides = []): array
    {
        $gym = Gym::factory()->create($gymOverrides + ['checkin_station_enabled' => true]);
        $stationToken = $gym->rotateCheckinStationToken();

        $member = Member::factory()->create($memberOverrides + ['gym_id' => $gym->id]);
        $token = $member->createToken('pwa', ['member-pwa', 'full'])->plainTextToken;

        return [$gym, $member, $token, $stationToken];
    }

    /**
     * Give the member a running membership, which the guards require.
     */
    private function giveMembership(Member $member, Gym $gym, array $overrides = []): Membership
    {
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);

        return Membership::factory()->create($overrides + [
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ]);
    }

    private function scan(string $token, array $payload): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(self::ENDPOINT, $payload);
    }

    // ------------------------------------------------------------------
    // Authentication and token validation
    // ------------------------------------------------------------------

    #[Test]
    public function scanning_without_a_session_is_rejected(): void
    {
        $gym = Gym::factory()->create(['checkin_station_enabled' => true]);
        $stationToken = $gym->rotateCheckinStationToken();

        $this->postJson(self::ENDPOINT, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])->assertStatus(401);
    }

    #[Test]
    public function gym_slug_and_station_token_are_required(): void
    {
        [, , $token] = $this->stationSetup();

        $this->scan($token, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gym_slug', 'station_token']);
    }

    #[Test]
    public function a_wrong_station_token_is_rejected(): void
    {
        [$gym, $member, $token] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => str_repeat('x', 48),
        ])
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'INVALID_STATION');

        $this->assertDatabaseCount('check_ins', 0);
    }

    #[Test]
    public function a_disabled_station_is_rejected_even_with_a_valid_token(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);
        $gym->update(['checkin_station_enabled' => false]);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'INVALID_STATION');
    }

    #[Test]
    public function another_gyms_station_token_does_not_work(): void
    {
        [$gym, $member, $token] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        $otherGym = Gym::factory()->create(['checkin_station_enabled' => true]);
        $otherToken = $otherGym->rotateCheckinStationToken();

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $otherToken,
        ])
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'INVALID_STATION');
    }

    #[Test]
    public function rotating_the_token_invalidates_the_printed_one(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        $gym->rotateCheckinStationToken();

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Toggling
    // ------------------------------------------------------------------

    #[Test]
    public function a_first_scan_checks_the_member_in(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'checked_in')
            ->assertJsonPath('data.gym.slug', $gym->slug);

        $this->assertDatabaseHas('check_ins', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_out_time' => null,
        ]);
    }

    #[Test]
    public function a_later_scan_checks_the_member_out(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        $checkin = CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subHour(),
            'check_out_time' => null,
        ]);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])
            ->assertOk()
            ->assertJsonPath('data.action', 'checked_out');

        $this->assertNotNull($checkin->fresh()->check_out_time);
    }

    #[Test]
    public function scanning_twice_in_a_row_does_not_check_the_member_back_out(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        $payload = ['gym_slug' => $gym->slug, 'station_token' => $stationToken];

        $this->scan($token, $payload)->assertJsonPath('data.action', 'checked_in');
        $this->scan($token, $payload)
            ->assertOk()
            ->assertJsonPath('data.action', 'already_checked_in');

        $this->assertDatabaseCount('check_ins', 1);
        $this->assertNull(CheckIn::first()->check_out_time);
    }

    #[Test]
    public function a_stale_check_in_starts_a_new_visit_instead_of_checking_out(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        // Forgotten check-out from yesterday: past the six-hour window, so the
        // scan has to open a new visit rather than close the old one.
        CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subHours(20),
            'check_out_time' => null,
        ]);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])->assertJsonPath('data.action', 'checked_in');

        $this->assertDatabaseCount('check_ins', 2);
    }

    #[Test]
    public function an_open_visit_at_another_location_is_not_closed_here(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        $otherGym = Gym::factory()->create();
        $elsewhere = CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $otherGym->id,
            'check_in_time' => now()->subHour(),
            'check_out_time' => null,
        ]);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])->assertJsonPath('data.action', 'checked_in');

        $this->assertNull($elsewhere->fresh()->check_out_time);
    }

    // ------------------------------------------------------------------
    // Access guards
    // ------------------------------------------------------------------

    #[Test]
    public function an_inactive_member_cannot_check_in(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup([], ['status' => 'inactive']);
        $this->giveMembership($member, $gym);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'MEMBER_INACTIVE');

        $this->assertDatabaseCount('check_ins', 0);
    }

    #[Test]
    public function a_member_without_an_active_membership_cannot_check_in(): void
    {
        [$gym, , $token, $stationToken] = $this->stationSetup();

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'NO_ACTIVE_MEMBERSHIP');
    }

    #[Test]
    public function a_membership_starting_in_the_future_cannot_check_in(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym, [
            'start_date' => now()->addWeek()->toDateString(),
        ]);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'MEMBERSHIP_NOT_STARTED');
    }

    #[Test]
    public function an_open_visit_can_be_checked_out_even_when_the_membership_lapsed(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();

        // No membership at all — someone standing inside must still get out.
        CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subHour(),
            'check_out_time' => null,
        ]);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])
            ->assertOk()
            ->assertJsonPath('data.action', 'checked_out');
    }

    #[Test]
    public function a_member_of_another_location_is_refused_when_the_gym_does_not_accept_them(): void
    {
        $owner = User::factory()->create();
        $home = Gym::factory()->create(['owner_id' => $owner->id]);
        $visited = Gym::factory()->create([
            'owner_id' => $owner->id,
            'checkin_station_enabled' => true,
            'cross_location_checkin_rule' => Gym::CHECKIN_RULE_OWN,
        ]);
        $stationToken = $visited->rotateCheckinStationToken();

        $member = Member::factory()->create(['gym_id' => $home->id]);
        $this->giveMembership($member, $home);
        $token = $member->createToken('pwa', ['member-pwa', 'full'])->plainTextToken;

        $this->scan($token, [
            'gym_slug' => $visited->slug,
            'station_token' => $stationToken,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'LOCATION_NOT_ALLOWED');
    }

    // ------------------------------------------------------------------
    // Logging and throttling
    // ------------------------------------------------------------------

    #[Test]
    public function a_check_in_is_written_to_the_access_log(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])->assertOk();

        $this->assertDatabaseHas('scanner_access_logs', [
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'scan_type' => StationCheckInService::SCAN_TYPE,
            'access_granted' => true,
        ]);
    }

    #[Test]
    public function a_check_out_is_not_written_to_the_access_log(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);

        CheckIn::create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subHour(),
            'check_out_time' => null,
        ]);

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])
            ->assertOk()
            ->assertJsonPath('data.action', 'checked_out');

        // The live log shows access decisions; leaving grants no access.
        $this->assertDatabaseCount('scanner_access_logs', 0);
    }

    #[Test]
    public function a_denied_scan_is_logged_with_its_reason(): void
    {
        [$gym, , $token, $stationToken] = $this->stationSetup();

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])->assertStatus(403);

        $log = ScannerAccessLog::first();

        $this->assertNotNull($log);
        $this->assertFalse($log->access_granted);
        $this->assertNotNull($log->denial_reason);
        $this->assertSame('NO_ACTIVE_MEMBERSHIP', $log->metadata['code']);
    }

    #[Test]
    public function repeated_invalid_scans_are_throttled(): void
    {
        [$gym, $member, $token] = $this->stationSetup();
        RateLimiter::clear('station-checkin:'.$member->id);

        for ($i = 0; $i < 10; $i++) {
            $this->scan($token, [
                'gym_slug' => $gym->slug,
                'station_token' => str_repeat('x', 48),
            ])->assertStatus(404);
        }

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => str_repeat('x', 48),
        ])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'TOO_MANY_ATTEMPTS');
    }

    #[Test]
    public function a_successful_scan_clears_the_throttle(): void
    {
        [$gym, $member, $token, $stationToken] = $this->stationSetup();
        $this->giveMembership($member, $gym);
        RateLimiter::clear('station-checkin:'.$member->id);

        for ($i = 0; $i < 9; $i++) {
            $this->scan($token, [
                'gym_slug' => $gym->slug,
                'station_token' => str_repeat('x', 48),
            ])->assertStatus(404);
        }

        $this->scan($token, [
            'gym_slug' => $gym->slug,
            'station_token' => $stationToken,
        ])->assertOk();

        $this->assertSame(0, RateLimiter::attempts('station-checkin:'.$member->id));
    }
}
