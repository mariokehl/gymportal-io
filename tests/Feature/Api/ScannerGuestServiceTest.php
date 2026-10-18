<?php

namespace Tests\Feature\Api;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\PendingSolariumRedemption;
use App\Models\ScannerAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the guest service QR code at the scanner (day pass, visit card,
 * solarium minutes) and the solarium terminal's redemption endpoints.
 */
class ScannerGuestServiceTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private GymScanner $entrance;

    private GymScanner $solarium;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create(['owner_id' => User::factory()->create()->id]);
        $this->entrance = $this->scanner($this->gym, GymScanner::TASK_CHECKIN, 'Eingang');
        $this->solarium = $this->scanner($this->gym, GymScanner::TASK_SOLARIUM, 'Solarium');
    }

    private function scanner(Gym $gym, string $task, string $name): GymScanner
    {
        return GymScanner::create([
            'gym_id' => $gym->id,
            'device_name' => $name,
            'device_task' => $task,
        ]);
    }

    private function guest(array $config = [], ?Gym $gym = null): Member
    {
        $member = Member::factory()->create([
            'gym_id' => ($gym ?? $this->gym)->id,
            'status' => 'extern',
        ]);

        MemberAccessConfig::create(array_merge([
            'member_id' => $member->id,
            'qr_code_enabled' => true,
        ], $config));

        return $member;
    }

    private function scan(Member $member, GymScanner $scanner)
    {
        return $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query([
                'scan_type' => 'guest_service',
                'member_id' => $member->id,
            ]));
    }

    #[Test]
    public function a_valid_day_pass_opens_the_entrance_and_books_a_check_in(): void
    {
        $guest = $this->guest([
            'day_pass_enabled' => true,
            'day_pass_valid_until' => now()->toDateString(),
            'visit_card_enabled' => true,
            'visit_card_entries' => 5,
        ]);

        $this->scan($guest, $this->entrance)
            ->assertOk()
            ->assertJson(['access_allowed' => true, 'service_allowed' => true]);

        $this->assertSame(1, CheckIn::where('member_id', $guest->id)->count());
        // The day pass covers the visit, no visit card entry is used.
        $this->assertSame(5, $guest->accessConfig->fresh()->visit_card_entries);
    }

    #[Test]
    public function a_visit_card_entry_is_used_per_visit(): void
    {
        $guest = $this->guest(['visit_card_enabled' => true, 'visit_card_entries' => 1]);

        $this->scan($guest, $this->entrance)
            ->assertOk()
            ->assertJsonPath('services.visit_card_entries', 0);

        $config = $guest->accessConfig->fresh();
        $this->assertSame(0, $config->visit_card_entries);
        $this->assertFalse($config->visit_card_enabled);
    }

    #[Test]
    public function solarium_minutes_alone_do_not_open_the_entrance(): void
    {
        $guest = $this->guest(['solarium_enabled' => true, 'solarium_minutes' => 20]);

        $this->scan($guest, $this->entrance)
            ->assertForbidden()
            ->assertJson(['access_allowed' => false, 'service_allowed' => false]);

        $this->assertSame(0, CheckIn::count());
    }

    #[Test]
    public function solarium_minutes_open_the_solarium_without_a_gym_check_in(): void
    {
        $guest = $this->guest(['solarium_enabled' => true, 'solarium_minutes' => 20]);

        $this->scan($guest, $this->solarium)
            ->assertOk()
            ->assertJson(['access_allowed' => true])
            ->assertJsonPath('services.solarium_minutes', 20);

        $this->assertSame(0, CheckIn::count());
        $this->assertTrue(ScannerAccessLog::where('member_id', $guest->id)->where('access_granted', true)->exists());
    }

    #[Test]
    public function guest_products_only_count_at_the_gym_they_were_bought_at(): void
    {
        // A sibling location of the same organisation.
        $sibling = Gym::factory()->create(['owner_id' => $this->gym->owner_id]);
        $siblingEntrance = $this->scanner($sibling, GymScanner::TASK_CHECKIN, 'Eingang 2');

        $guest = $this->guest([
            'day_pass_enabled' => true,
            'day_pass_valid_until' => now()->toDateString(),
        ]);

        $this->scan($guest, $siblingEntrance)->assertForbidden();

        $this->assertSame(0, CheckIn::count());
    }

    #[Test]
    public function a_guest_of_an_unrelated_gym_is_unknown(): void
    {
        $guest = $this->guest(['visit_card_enabled' => true, 'visit_card_entries' => 3], Gym::factory()->create());

        $this->scan($guest, $this->entrance)->assertNotFound();

        $this->assertSame(3, $guest->accessConfig->fresh()->visit_card_entries);
    }

    #[Test]
    public function a_dispenser_never_serves_a_guest(): void
    {
        $dispenser = $this->scanner($this->gym, GymScanner::TASK_DISPENSER, 'Zapfstelle');
        $guest = $this->guest(['visit_card_enabled' => true, 'visit_card_entries' => 3]);

        $this->scan($guest, $dispenser)->assertForbidden();

        $this->assertSame(3, $guest->accessConfig->fresh()->visit_card_entries);
    }

    #[Test]
    public function only_solarium_devices_may_poll_redemptions(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->entrance->getPlainTextToken())
            ->getJson('/api/scanner/pending-solarium-redemptions')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$this->solarium->getPlainTextToken())
            ->getJson('/api/scanner/pending-solarium-redemptions')
            ->assertOk()
            ->assertJsonStructure(['redemptions', 'has_recent_checkins']);
    }

    #[Test]
    public function a_failed_redemption_returns_the_minutes(): void
    {
        $guest = $this->guest(['solarium_enabled' => false, 'solarium_minutes' => 0]);
        $redemption = PendingSolariumRedemption::create([
            'member_id' => $guest->id,
            'gym_id' => $this->gym->id,
            'minutes' => 12,
            'status' => PendingSolariumRedemption::STATUS_PENDING,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->solarium->getPlainTextToken())
            ->postJson("/api/scanner/pending-solarium-redemptions/{$redemption->id}/acknowledge", [
                'status' => 'failed',
                'reason' => 'relay_error',
            ])
            ->assertOk()
            ->assertJson(['status' => PendingSolariumRedemption::STATUS_FAILED]);

        $config = $guest->accessConfig->fresh();
        $this->assertSame(12, $config->solarium_minutes);
        $this->assertTrue($config->solarium_enabled);
    }

    #[Test]
    public function a_terminal_cannot_acknowledge_a_redemption_of_another_gym(): void
    {
        $otherGym = Gym::factory()->create();
        $guest = $this->guest([], $otherGym);
        $redemption = PendingSolariumRedemption::create([
            'member_id' => $guest->id,
            'gym_id' => $otherGym->id,
            'minutes' => 12,
            'status' => PendingSolariumRedemption::STATUS_PENDING,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->solarium->getPlainTextToken())
            ->postJson("/api/scanner/pending-solarium-redemptions/{$redemption->id}/acknowledge", ['status' => 'completed'])
            ->assertNotFound();

        $this->assertTrue($redemption->fresh()->isPending());
    }

    #[Test]
    public function the_solarium_stays_closed_for_minors(): void
    {
        $guest = $this->guest(['solarium_enabled' => true, 'solarium_minutes' => 20]);
        $guest->update(['birth_date' => now()->subYears(17)->toDateString()]);

        $this->scan($guest, $this->solarium)
            ->assertForbidden()
            ->assertJson(['access_allowed' => false]);
    }
}
