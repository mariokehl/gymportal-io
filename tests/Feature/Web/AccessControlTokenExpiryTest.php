<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the optional expiry date of a device's API token. An expiring token
 * locks members out of the gym, so "never expires" has to survive every path.
 */
class AccessControlTokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    private function ownerWithGym(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return [$owner->fresh(), $gym];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'device_name' => 'Eingang',
            'device_task' => GymScanner::TASK_CHECKIN,
        ], $overrides);
    }

    /*
    | Creating
    */

    #[Test]
    public function a_device_can_be_created_with_an_expiry(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $expiry = now()->addMonths(12)->toDateString();

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), $this->payload([
                'token_expires_at' => $expiry,
            ]))
            ->assertOk();

        $this->assertSame(
            $expiry,
            GymScanner::where('gym_id', $gym->id)->value('token_expires_at')?->toDateString()
        );
    }

    #[Test]
    public function a_device_can_be_created_without_an_expiry(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), $this->payload([
                'token_expires_at' => null,
            ]))
            ->assertOk();

        $this->assertNull(GymScanner::where('gym_id', $gym->id)->value('token_expires_at'));
    }

    #[Test]
    public function omitting_the_field_leaves_the_token_without_an_expiry(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        // Nothing may quietly add an expiry — that would eventually shut a door.
        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), $this->payload())
            ->assertOk();

        $this->assertNull(GymScanner::where('gym_id', $gym->id)->value('token_expires_at'));
    }

    #[Test]
    public function an_expiry_in_the_past_is_rejected(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), $this->payload([
                'token_expires_at' => now()->subDay()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('token_expires_at');
    }

    #[Test]
    public function todays_date_is_rejected(): void
    {
        [$owner] = $this->ownerWithGym();

        // Would expire the same day it is issued.
        $this->actingAs($owner)
            ->postJson(route('access-control.scanners.store'), $this->payload([
                'token_expires_at' => now()->toDateString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('token_expires_at');
    }

    /*
    | Updating
    */

    private function scannerFor(Gym $gym, ?string $expiresAt = null): GymScanner
    {
        return GymScanner::create([
            'gym_id' => $gym->id,
            'device_name' => 'Eingang',
            'device_task' => GymScanner::TASK_CHECKIN,
            'token_expires_at' => $expiresAt,
        ]);
    }

    #[Test]
    public function an_expiry_can_be_added_to_an_existing_device(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $scanner = $this->scannerFor($gym);
        $expiry = now()->addMonths(12)->toDateString();

        $this->actingAs($owner)
            ->putJson(route('access-control.scanners.update', $scanner), $this->payload([
                'token_expires_at' => $expiry,
            ]))
            ->assertOk();

        $this->assertSame($expiry, $scanner->fresh()->token_expires_at?->toDateString());
    }

    #[Test]
    public function an_expiry_can_be_cleared_again(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $scanner = $this->scannerFor($gym, now()->addMonth()->toDateString());

        // "Token läuft nie ab" sends null — the device must lose its expiry,
        // otherwise the operator cannot undo a date they set by mistake.
        $this->actingAs($owner)
            ->putJson(route('access-control.scanners.update', $scanner), $this->payload([
                'token_expires_at' => null,
            ]))
            ->assertOk();

        $this->assertNull($scanner->fresh()->token_expires_at);
    }

    #[Test]
    public function an_expiry_can_be_moved_further_out(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $scanner = $this->scannerFor($gym, now()->addWeek()->toDateString());
        $later = now()->addYear()->toDateString();

        $this->actingAs($owner)
            ->putJson(route('access-control.scanners.update', $scanner), $this->payload([
                'token_expires_at' => $later,
            ]))
            ->assertOk();

        $this->assertSame($later, $scanner->fresh()->token_expires_at?->toDateString());
    }

    #[Test]
    public function the_expiry_is_visible_to_the_frontend(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $this->scannerFor($gym, now()->addMonths(12)->toDateString());

        // The edit form pre-fills from this value.
        $this->actingAs($owner)
            ->get(route('access-control.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('scanners.0.token_expires_at'));
    }

    /*
    | Effect on access
    */

    #[Test]
    public function a_device_with_a_future_expiry_still_opens(): void
    {
        [, $gym] = $this->ownerWithGym();
        $scanner = $this->scannerFor($gym, now()->addYear()->toDateString());

        $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/ping')
            ->assertOk();
    }

    #[Test]
    public function a_device_without_an_expiry_keeps_working_indefinitely(): void
    {
        [, $gym] = $this->ownerWithGym();
        $scanner = $this->scannerFor($gym);

        $this->travel(5)->years();

        $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/ping')
            ->assertOk();
    }

    #[Test]
    public function a_device_stops_working_once_its_expiry_passes(): void
    {
        [, $gym] = $this->ownerWithGym();
        $scanner = $this->scannerFor($gym, now()->addMonth()->toDateString());

        $this->travel(2)->months();

        // This is the failure mode the checkbox exists to avoid.
        $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/ping')
            ->assertStatus(401);
    }
}
