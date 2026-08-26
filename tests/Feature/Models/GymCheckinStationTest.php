<?php

namespace Tests\Feature\Models;

use App\Models\Gym;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The gym-side pieces of the printed check-in station: minting, matching and
 * rotating the token the printed sheet carries.
 */
class GymCheckinStationTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Token
    // ------------------------------------------------------------------

    #[Test]
    public function a_fresh_gym_has_no_station(): void
    {
        $gym = Gym::factory()->create();

        $this->assertFalse($gym->hasCheckinStation());
    }

    #[Test]
    public function enabling_alone_is_not_enough_without_a_token(): void
    {
        $gym = Gym::factory()->create(['checkin_station_enabled' => true]);

        $this->assertFalse($gym->hasCheckinStation());
    }

    #[Test]
    public function rotating_issues_a_token_and_replaces_the_previous_one(): void
    {
        $gym = Gym::factory()->create(['checkin_station_enabled' => true]);

        $first = $gym->rotateCheckinStationToken();
        $this->assertTrue($gym->hasCheckinStation());
        $this->assertTrue($gym->matchesCheckinStationToken($first));

        $second = $gym->rotateCheckinStationToken();
        $this->assertNotSame($first, $second);
        $this->assertFalse($gym->matchesCheckinStationToken($first));
        $this->assertTrue($gym->matchesCheckinStationToken($second));
    }

    #[Test]
    public function an_empty_token_never_matches(): void
    {
        $gym = Gym::factory()->create();

        $this->assertFalse($gym->matchesCheckinStationToken(''));
    }

    #[Test]
    public function the_token_is_never_exposed_through_the_api(): void
    {
        $gym = Gym::factory()->create(['checkin_station_enabled' => true]);
        $gym->rotateCheckinStationToken();

        $this->assertArrayNotHasKey('checkin_station_token', $gym->fresh()->toArray());
        $this->assertArrayNotHasKey('checkin_station_token', $gym->fresh()->getMemberAppData());
    }
}
