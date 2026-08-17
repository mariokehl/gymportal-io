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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the cross-location half of GET /api/scanner/verify-membership.
 *
 * Two rules have to agree before a visiting member gets in: the visited
 * location must accept their home location, and their contract must cover the
 * visited location. Each test isolates one of them.
 */
class ScannerCrossLocationCheckinTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $berlin;

    private Gym $hamburg;

    private GymScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();

        // One organisation: both locations share an owner.
        $this->owner = User::factory()->create();
        $this->berlin = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Berlin']);
        $this->hamburg = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Hamburg']);

        $this->scanner = GymScanner::create([
            'gym_id' => $this->berlin->id,
            'device_name' => 'Eingang Berlin',
            'device_task' => GymScanner::TASK_CHECKIN,
        ]);
    }

    /**
     * An active member of $gym holding a plan with the given location scope.
     *
     * @param  array<int>  $allowedGymIds
     */
    private function memberOf(Gym $gym, string $scope = MembershipPlan::SCOPE_OWN, array $allowedGymIds = []): Member
    {
        $member = Member::factory()->create([
            'gym_id' => $gym->id,
            'status' => 'active',
        ]);

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'location_scope' => $scope,
        ]);

        if ($allowedGymIds !== []) {
            $plan->allowedGyms()->sync($allowedGymIds);
        }

        Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        return $member;
    }

    private function scanQr(Member $member)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->scanner->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query([
                'scan_type' => 'qr_code',
                'member_id' => $member->id,
            ]));
    }

    /**
     * @param  array<int>  $allowedGymIds
     */
    private function setBerlinRule(string $rule, array $allowedGymIds = []): void
    {
        $this->berlin->update(['cross_location_checkin_rule' => $rule]);
        $this->berlin->allowedCheckinGyms()->sync($allowedGymIds);
    }

    /*
    | The location rule
    */

    #[Test]
    public function a_visiting_member_is_denied_while_the_location_only_admits_its_own(): void
    {
        // 'own' is the default and the behaviour before this feature existed.
        $visitor = $this->memberOf($this->hamburg, MembershipPlan::SCOPE_ALL);

        $this->scanQr($visitor)->assertStatus(403);

        $this->assertSame(0, CheckIn::where('member_id', $visitor->id)->count());
    }

    #[Test]
    public function a_visiting_member_is_admitted_when_both_rules_allow_it(): void
    {
        $this->setBerlinRule(Gym::CHECKIN_RULE_ALL);
        $visitor = $this->memberOf($this->hamburg, MembershipPlan::SCOPE_ALL);

        $this->scanQr($visitor)
            ->assertOk()
            ->assertJson(['access_allowed' => true]);
    }

    #[Test]
    public function a_selected_rule_only_admits_the_listed_locations(): void
    {
        $munich = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone München']);

        $this->setBerlinRule(Gym::CHECKIN_RULE_SELECTED, [$this->hamburg->id]);

        $allowed = $this->memberOf($this->hamburg, MembershipPlan::SCOPE_ALL);
        $notListed = $this->memberOf($munich, MembershipPlan::SCOPE_ALL);

        $this->scanQr($allowed)->assertOk();
        $this->scanQr($notListed)->assertStatus(403);
    }

    /*
    | The contract rule
    */

    #[Test]
    public function an_open_location_still_denies_a_contract_bound_to_the_home_location(): void
    {
        $this->setBerlinRule(Gym::CHECKIN_RULE_ALL);
        $visitor = $this->memberOf($this->hamburg, MembershipPlan::SCOPE_OWN);

        $this->scanQr($visitor)->assertStatus(403);

        $this->assertSame(0, CheckIn::where('member_id', $visitor->id)->count());
    }

    #[Test]
    public function a_contract_covering_selected_locations_admits_only_those(): void
    {
        $munich = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $this->setBerlinRule(Gym::CHECKIN_RULE_ALL);

        $covering = $this->memberOf($this->hamburg, MembershipPlan::SCOPE_SELECTED, [$this->berlin->id]);
        $elsewhere = $this->memberOf($this->hamburg, MembershipPlan::SCOPE_SELECTED, [$munich->id]);

        $this->scanQr($covering)->assertOk();
        $this->scanQr($elsewhere)->assertStatus(403);
    }

    /*
    | Organisation boundary — the rules never reach beyond it
    */

    #[Test]
    public function a_member_of_another_organisation_stays_unknown_even_with_every_rule_open(): void
    {
        $this->setBerlinRule(Gym::CHECKIN_RULE_ALL);

        // Its own owner, so a different organisation entirely.
        $outsideGym = Gym::factory()->create();
        $outsider = $this->memberOf($outsideGym, MembershipPlan::SCOPE_ALL);

        // 404, not 403: an unrelated studio's member must not even be findable.
        $this->scanQr($outsider)->assertStatus(404);

        $this->assertSame(0, CheckIn::where('member_id', $outsider->id)->count());
    }

    #[Test]
    public function an_nfc_card_of_another_organisation_stays_unknown(): void
    {
        $this->setBerlinRule(Gym::CHECKIN_RULE_ALL);

        $outsideGym = Gym::factory()->create();
        $outsider = $this->memberOf($outsideGym, MembershipPlan::SCOPE_ALL);
        MemberAccessConfig::create([
            'member_id' => $outsider->id,
            'nfc_enabled' => true,
            'nfc_uid' => '04ABCDEF',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->scanner->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query([
                'scan_type' => 'nfc_card',
                'nfc_card_id' => '04ABCDEF',
            ]))
            ->assertStatus(404);
    }

    /*
    | Bookkeeping
    */

    #[Test]
    public function a_cross_location_check_in_is_booked_against_the_visited_location(): void
    {
        $this->setBerlinRule(Gym::CHECKIN_RULE_ALL);
        $visitor = $this->memberOf($this->hamburg, MembershipPlan::SCOPE_ALL);

        $this->scanQr($visitor)->assertOk();

        // The visit happened in Berlin, so Berlin's statistics must count it.
        $this->assertDatabaseHas('check_ins', [
            'member_id' => $visitor->id,
            'gym_id' => $this->berlin->id,
        ]);
    }

    #[Test]
    public function the_log_records_the_home_location_and_the_denial_kind(): void
    {
        $this->setBerlinRule(Gym::CHECKIN_RULE_ALL);
        $visitor = $this->memberOf($this->hamburg, MembershipPlan::SCOPE_OWN);

        $this->scanQr($visitor)->assertStatus(403);

        $log = ScannerAccessLog::where('member_id', $visitor->id)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($this->berlin->id, $log->gym_id);
        $this->assertSame($this->hamburg->id, $log->home_gym_id);
        $this->assertTrue($log->isCrossLocation());
        $this->assertSame(ScannerAccessLog::DENIAL_KIND_CONTRACT, $log->denial_kind);
    }

    #[Test]
    public function a_denial_by_the_location_rule_is_marked_as_such(): void
    {
        // Berlin stays on 'own', so the location blocks before the contract is read.
        $visitor = $this->memberOf($this->hamburg, MembershipPlan::SCOPE_ALL);

        $this->scanQr($visitor)->assertStatus(403);

        $log = ScannerAccessLog::where('member_id', $visitor->id)->latest('id')->first();

        $this->assertSame(ScannerAccessLog::DENIAL_KIND_LOCATION, $log->denial_kind);
    }

    #[Test]
    public function an_own_member_is_unaffected_and_logs_no_foreign_origin(): void
    {
        $local = $this->memberOf($this->berlin, MembershipPlan::SCOPE_OWN);

        $this->scanQr($local)->assertOk();

        $log = ScannerAccessLog::where('member_id', $local->id)->latest('id')->first();

        $this->assertFalse($log->isCrossLocation());
        $this->assertDatabaseHas('check_ins', [
            'member_id' => $local->id,
            'gym_id' => $this->berlin->id,
        ]);
    }

    /*
    | Guest access
    */

    #[Test]
    public function a_guest_of_another_location_is_denied_even_when_the_location_is_open(): void
    {
        $this->setBerlinRule(Gym::CHECKIN_RULE_ALL);

        // Guest access carries no contract, so nothing can cover another location.
        $guest = Member::factory()->create([
            'gym_id' => $this->hamburg->id,
            'status' => 'active',
            'guest_access' => true,
        ]);

        $this->scanQr($guest)->assertStatus(403);

        $this->assertSame(0, CheckIn::where('member_id', $guest->id)->count());
    }
}
