<?php

namespace Tests\Feature\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Services\CrossLocationAccessService;
use App\Services\ScannerValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrossLocationAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private CrossLocationAccessService $service;

    private User $owner;

    private Gym $berlin;

    private Gym $hamburg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CrossLocationAccessService::class);

        $this->owner = User::factory()->create();
        $this->berlin = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Berlin']);
        $this->hamburg = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Hamburg']);
    }

    /**
     * @param  array<int>  $allowedGymIds
     */
    private function memberWithPlan(Gym $gym, string $scope, array $allowedGymIds = []): array
    {
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'active']);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'location_scope' => $scope]);

        if ($allowedGymIds !== []) {
            $plan->allowedGyms()->sync($allowedGymIds);
        }

        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        return [$member, $membership];
    }

    #[Test]
    public function the_home_location_never_needs_either_rule(): void
    {
        [$member, $membership] = $this->memberWithPlan($this->berlin, MembershipPlan::SCOPE_OWN);

        [$allowed] = $this->service->check($this->berlin, $member, $membership);

        $this->assertTrue($allowed);
    }

    #[Test]
    public function it_names_the_location_as_the_blocking_rule(): void
    {
        [$member, $membership] = $this->memberWithPlan($this->hamburg, MembershipPlan::SCOPE_ALL);

        [$allowed, $reason, $kind] = $this->service->check($this->berlin, $member, $membership);

        $this->assertFalse($allowed);
        $this->assertSame(CrossLocationAccessService::REASON_LOCATION, $kind);
        $this->assertStringContainsString('FitZone Hamburg', $reason);
    }

    #[Test]
    public function it_names_the_contract_as_the_blocking_rule(): void
    {
        $this->berlin->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_ALL]);
        [$member, $membership] = $this->memberWithPlan($this->hamburg, MembershipPlan::SCOPE_OWN);

        [$allowed, $reason, $kind] = $this->service->check($this->berlin, $member, $membership);

        $this->assertFalse($allowed);
        $this->assertSame(CrossLocationAccessService::REASON_CONTRACT, $kind);
        $this->assertStringContainsString($membership->membershipPlan->name, $reason);
    }

    #[Test]
    public function a_member_without_a_membership_never_leaves_their_location(): void
    {
        $this->berlin->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_ALL]);
        $member = Member::factory()->create(['gym_id' => $this->hamburg->id, 'status' => 'active']);

        [$allowed, , $kind] = $this->service->check($this->berlin, $member, null);

        $this->assertFalse($allowed);
        $this->assertSame(CrossLocationAccessService::REASON_CONTRACT, $kind);
    }

    #[Test]
    public function the_organisation_boundary_holds_even_with_every_rule_open(): void
    {
        $this->berlin->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_ALL]);

        $outsideGym = Gym::factory()->create();
        [$member, $membership] = $this->memberWithPlan($outsideGym, MembershipPlan::SCOPE_ALL);

        [$allowed, , $kind] = $this->service->check($this->berlin, $member, $membership);

        $this->assertFalse($allowed);
        $this->assertSame(CrossLocationAccessService::REASON_LOCATION, $kind);
    }

    /*
    | The effect preview shown on the contract's Standorte tab
    */

    #[Test]
    public function the_effect_preview_reports_which_rule_blocks_each_location(): void
    {
        // Hamburg accepts everyone, so only the contract can block it.
        $this->hamburg->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_ALL]);
        $munich = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone München']);

        $effect = collect($this->service->contractEffect(
            $this->berlin,
            MembershipPlan::SCOPE_ALL,
            []
        ))->keyBy('id');

        $this->assertSame('Heimatstandort', $effect[$this->berlin->id]['reason']);
        $this->assertTrue($effect[$this->hamburg->id]['allowed']);
        // Munich is still on 'own', so the location blocks despite the contract.
        $this->assertFalse($effect[$munich->id]['allowed']);
        $this->assertSame('Standort erlaubt nicht', $effect[$munich->id]['reason']);
    }

    #[Test]
    public function the_effect_preview_reports_a_contract_that_does_not_cover_a_location(): void
    {
        $this->hamburg->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_ALL]);

        $effect = collect($this->service->contractEffect(
            $this->berlin,
            MembershipPlan::SCOPE_OWN,
            []
        ))->keyBy('id');

        $this->assertFalse($effect[$this->hamburg->id]['allowed']);
        $this->assertSame('Vertrag erlaubt nicht', $effect[$this->hamburg->id]['reason']);
    }

    /*
    | QR validation across locations
    */

    #[Test]
    public function a_visiting_members_qr_code_validates_against_their_home_key(): void
    {
        $this->berlin->generateScannerSecretKey();
        $this->hamburg->generateScannerSecretKey();

        $member = Member::factory()->create(['gym_id' => $this->hamburg->id, 'status' => 'active']);
        $qr = app(ScannerValidationService::class)->generateSecureQrCode($member);

        // While Berlin admits only its own members it does not know Hamburg's key.
        $this->assertFalse(
            $this->berlin->validateHash($qr['member_id'], $qr['timestamp'], $qr['hash'])
        );

        $this->berlin->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_ALL]);

        // Once Hamburg is accepted, its key is tried as a fallback.
        $this->assertTrue(
            $this->berlin->fresh()->validateHash($qr['member_id'], $qr['timestamp'], $qr['hash'])
        );
    }

    #[Test]
    public function the_accepted_keys_never_include_another_organisation(): void
    {
        $this->berlin->generateScannerSecretKey();
        $this->hamburg->generateScannerSecretKey();

        $outsideGym = Gym::factory()->create();
        $outsideGym->generateScannerSecretKey();

        $this->berlin->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_ALL]);
        $keys = $this->berlin->fresh()->acceptedCheckinKeys();

        $this->assertContains($this->hamburg->scanner_secret_key, $keys);
        $this->assertNotContains($outsideGym->scanner_secret_key, $keys);
        $this->assertNotContains($this->berlin->scanner_secret_key, $keys);
    }

    #[Test]
    public function a_selected_rule_only_hands_out_the_listed_keys(): void
    {
        $this->berlin->generateScannerSecretKey();
        $this->hamburg->generateScannerSecretKey();

        $munich = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $munich->generateScannerSecretKey();

        $this->berlin->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_SELECTED]);
        $this->berlin->allowedCheckinGyms()->sync([$this->hamburg->id]);

        $keys = $this->berlin->fresh()->acceptedCheckinKeys();

        $this->assertContains($this->hamburg->scanner_secret_key, $keys);
        $this->assertNotContains($munich->scanner_secret_key, $keys);
    }

    #[Test]
    public function an_own_rule_hands_out_no_foreign_keys_at_all(): void
    {
        $this->berlin->generateScannerSecretKey();
        $this->hamburg->generateScannerSecretKey();

        // The default, and what every gym looked like before this feature.
        $this->assertSame([], $this->berlin->acceptedCheckinKeys());
    }
}
