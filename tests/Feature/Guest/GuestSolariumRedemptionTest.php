<?php

namespace Tests\Feature\Guest;

use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\PendingSolariumRedemption;
use App\Models\ScannerAccessLog;
use App\Services\SolariumRedemptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Redeeming solarium minutes from the guest app.
 */
class GuestSolariumRedemptionTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private GymScanner $solarium;

    private Member $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create(['guest_enabled' => true]);
        $this->solarium = GymScanner::create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Solarium',
            'device_task' => GymScanner::TASK_SOLARIUM,
        ]);

        $this->guest = Member::factory()->create(['gym_id' => $this->gym->id, 'status' => 'extern']);
        MemberAccessConfig::create([
            'member_id' => $this->guest->id,
            'qr_code_enabled' => true,
            'solarium_enabled' => true,
            'solarium_minutes' => 30,
        ]);
    }

    private function scannedAtSolarium(?GymScanner $scanner = null): void
    {
        $scanner ??= $this->solarium;

        ScannerAccessLog::create([
            'gym_id' => $scanner->gym_id,
            'device_number' => $scanner->device_number,
            'member_id' => $this->guest->id,
            'scan_type' => 'guest_service',
            'access_granted' => true,
        ]);
    }

    private function redeem(int $minutes)
    {
        $token = $this->guest->createToken('guest-pwa-full', ['guest-pwa', 'full'])->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/guests/solarium/redeem', ['minutes' => $minutes]);
    }

    private function minutes(): int
    {
        return (int) $this->guest->accessConfig()->value('solarium_minutes');
    }

    #[Test]
    public function redeeming_requires_a_scan_at_the_solarium_first(): void
    {
        $this->redeem(10)->assertStatus(409);

        $this->assertSame(30, $this->minutes());
    }

    #[Test]
    public function a_scan_at_the_entrance_does_not_count(): void
    {
        $entrance = GymScanner::create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Eingang',
            'device_task' => GymScanner::TASK_CHECKIN,
        ]);
        $this->scannedAtSolarium($entrance);

        $this->redeem(10)->assertStatus(409);
    }

    #[Test]
    public function redeeming_reserves_the_minutes(): void
    {
        $this->scannedAtSolarium();

        $this->redeem(10)
            ->assertOk()
            ->assertJsonPath('data.remaining_minutes', 20);

        $this->assertSame(20, $this->minutes());
        $this->assertSame(1, PendingSolariumRedemption::where('gym_id', $this->gym->id)->where('status', PendingSolariumRedemption::STATUS_PENDING)->count());
    }

    #[Test]
    public function more_minutes_than_available_cannot_be_redeemed(): void
    {
        $this->scannedAtSolarium();

        $this->redeem(31)->assertStatus(409);

        $this->assertSame(30, $this->minutes());
    }

    #[Test]
    public function cancelling_returns_the_minutes(): void
    {
        $this->scannedAtSolarium();
        $id = $this->redeem(10)->json('data.redemption_id');

        $token = $this->guest->createToken('guest-pwa-full', ['guest-pwa', 'full'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/guests/solarium/redemption/{$id}")
            ->assertOk();

        $this->assertSame(30, $this->minutes());
        $this->assertSame(PendingSolariumRedemption::STATUS_CANCELLED, PendingSolariumRedemption::find($id)->status);
    }

    #[Test]
    public function unacknowledged_redemptions_expire_and_return_the_minutes(): void
    {
        $this->scannedAtSolarium();
        $id = $this->redeem(10)->json('data.redemption_id');

        $this->travel(PendingSolariumRedemption::EXPIRY_SECONDS + 1)->seconds();

        $this->assertSame(1, app(SolariumRedemptionService::class)->expireStale());
        $this->assertSame(30, $this->minutes());
        $this->assertSame(PendingSolariumRedemption::STATUS_EXPIRED, PendingSolariumRedemption::find($id)->status);
    }

    #[Test]
    public function minors_cannot_redeem_solarium_minutes(): void
    {
        $this->guest->update(['birth_date' => now()->subYears(17)->toDateString()]);
        $this->scannedAtSolarium();

        $this->redeem(10)->assertStatus(409);

        $this->assertSame(30, $this->minutes());
    }
}
