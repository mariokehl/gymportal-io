<?php

namespace Tests\Feature\Pwa;

use App\Models\Gym;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The station endpoint's behaviour for a member who is not signed in.
 *
 * The PWA sends them through the login and back to the scan URL, carrying the
 * token in the query. This test pins the API half of that: the scan is refused
 * without a session, and the very same token works once one exists.
 */
class StationLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_same_printed_token_works_once_the_member_has_signed_in(): void
    {
        $gym = Gym::factory()->create(['checkin_station_enabled' => true]);
        $stationToken = $gym->rotateCheckinStationToken();

        $payload = ['gym_slug' => $gym->slug, 'station_token' => $stationToken];

        // Scanned before signing in.
        $this->postJson('/api/pwa/member/checkin/toggle', $payload)->assertStatus(401);

        // Same sheet, same token, now with a session — this is what the PWA
        // replays after the login redirect returns to /:slug/scan?t=...
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $token = $member->createToken('pwa', ['member-pwa', 'full'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/pwa/member/checkin/toggle', $payload)
            ->assertStatus(403)
            // Reached the access guards, so the token itself was accepted.
            ->assertJsonPath('error_code', 'NO_ACTIVE_MEMBERSHIP');
    }

    #[Test]
    public function an_anonymous_session_may_also_use_the_station(): void
    {
        $gym = Gym::factory()->create(['checkin_station_enabled' => true]);
        $stationToken = $gym->rotateCheckinStationToken();

        // Anonymous sessions can already read and end check-ins, so the station
        // must not be stricter — that would strand members mid-visit.
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $token = $member->createToken('pwa', ['member-pwa', 'anonymous'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/pwa/member/checkin/toggle', [
                'gym_slug' => $gym->slug,
                'station_token' => $stationToken,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'NO_ACTIVE_MEMBERSHIP');
    }
}
