<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Operator-facing configuration of the printed check-in station.
 */
class CheckinStationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;

        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $this->owner->id]);

        $this->owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $this->owner->fresh();
    }

    #[Test]
    public function enabling_the_station_mints_a_token_and_returns_the_scan_url(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson(route('access-control.checkin-station.update'), [
                'checkin_station_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('checkin_station.enabled', true)
            ->assertJsonPath('checkin_station.has_token', true);

        $this->assertTrue($this->gym->fresh()->hasCheckinStation());

        // The printed sheet has to carry the PWA URL, the slug and the token.
        $scanUrl = $response->json('checkin_station.scan_url');
        $this->assertStringStartsWith(config('app.pwa_url').'/'.$this->gym->slug.'/scan?t=', $scanUrl);
        $this->assertStringContainsString(
            $this->gym->fresh()->getAttributes()['checkin_station_token'],
            $scanUrl
        );
    }

    #[Test]
    public function enabling_again_keeps_the_token_that_is_already_printed(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('access-control.checkin-station.update'), [
                'checkin_station_enabled' => true,
            ])->assertOk();

        $token = $this->gym->fresh()->getAttributes()['checkin_station_token'];

        // Saving the card again must not silently invalidate every sheet in
        // circulation — only the explicit "Code erneuern" action may do that.
        $this->actingAs($this->owner)
            ->putJson(route('access-control.checkin-station.update'), [
                'checkin_station_enabled' => true,
            ])->assertOk();

        $this->assertSame($token, $this->gym->fresh()->getAttributes()['checkin_station_token']);
    }

    #[Test]
    public function disabling_the_station_discards_the_token(): void
    {
        // A disabled station has no code to print, and parking the old one would
        // let a leaked sheet work again the moment the feature is switched back
        // on. Disabling therefore destroys it.
        $this->gym->rotateCheckinStationToken();
        $this->gym->update(['checkin_station_enabled' => true]);

        $this->actingAs($this->owner)
            ->putJson(route('access-control.checkin-station.update'), [
                'checkin_station_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('checkin_station.enabled', false)
            ->assertJsonPath('checkin_station.has_token', false)
            ->assertJsonPath('checkin_station.scan_url', null);

        $fresh = $this->gym->fresh();

        $this->assertFalse($fresh->hasCheckinStation());
        $this->assertNull($fresh->getAttributes()['checkin_station_token']);
    }

    #[Test]
    public function a_sheet_printed_before_disabling_stops_working(): void
    {
        $this->gym->update(['checkin_station_enabled' => true]);
        $printed = $this->gym->rotateCheckinStationToken();

        $this->actingAs($this->owner)
            ->putJson(route('access-control.checkin-station.update'), [
                'checkin_station_enabled' => false,
            ])->assertOk();

        $this->assertFalse($this->gym->fresh()->matchesCheckinStationToken($printed));
    }

    #[Test]
    public function re_enabling_mints_a_new_token_rather_than_reviving_the_old_one(): void
    {
        $this->gym->update(['checkin_station_enabled' => true]);
        $printed = $this->gym->rotateCheckinStationToken();

        $this->actingAs($this->owner)
            ->putJson(route('access-control.checkin-station.update'), [
                'checkin_station_enabled' => false,
            ])->assertOk();

        $this->actingAs($this->owner)
            ->putJson(route('access-control.checkin-station.update'), [
                'checkin_station_enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('checkin_station.has_token', true);

        $fresh = $this->gym->fresh();

        $this->assertTrue($fresh->hasCheckinStation());
        $this->assertFalse($fresh->matchesCheckinStationToken($printed));
    }

    #[Test]
    public function disabling_an_already_disabled_station_is_harmless(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('access-control.checkin-station.update'), [
                'checkin_station_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('checkin_station.has_token', false);

        $this->assertFalse($this->gym->fresh()->hasCheckinStation());
    }

    #[Test]
    public function regenerating_replaces_the_token(): void
    {
        $this->gym->update(['checkin_station_enabled' => true]);
        $old = $this->gym->rotateCheckinStationToken();

        $this->actingAs($this->owner)
            ->postJson(route('access-control.checkin-station.regenerate'))
            ->assertOk()
            ->assertJsonPath('checkin_station.has_token', true);

        $this->assertFalse($this->gym->fresh()->matchesCheckinStationToken($old));
    }

    #[Test]
    public function a_user_without_manage_rights_cannot_configure_the_station(): void
    {
        $staff = User::factory()->create(['current_gym_id' => $this->gym->id]);

        $this->actingAs($staff->fresh())
            ->putJson(route('access-control.checkin-station.update'), [
                'checkin_station_enabled' => true,
            ])
            ->assertForbidden();

        $this->assertFalse($this->gym->fresh()->hasCheckinStation());
    }

    #[Test]
    public function a_user_without_manage_rights_cannot_regenerate_the_token(): void
    {
        $this->gym->update(['checkin_station_enabled' => true]);
        $old = $this->gym->rotateCheckinStationToken();
        $staff = User::factory()->create(['current_gym_id' => $this->gym->id]);

        $this->actingAs($staff->fresh())
            ->postJson(route('access-control.checkin-station.regenerate'))
            ->assertForbidden();

        $this->assertTrue($this->gym->fresh()->matchesCheckinStationToken($old));
    }
}
