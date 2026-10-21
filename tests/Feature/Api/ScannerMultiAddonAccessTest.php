<?php

namespace Tests\Feature\Api;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessLog;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A dispenser settling against several usage add-ons, including the ones a
 * sibling location sells to its own members.
 *
 * The scenario from the field: a member of FitZone München with a flex
 * contract and the Munich drink package visits FitZone Berlin and should be
 * served at the Berlin dispenser.
 */
class ScannerMultiAddonAccessTest extends TestCase
{
    use RefreshDatabase;

    private Gym $berlin;

    private Gym $munich;

    private Addon $berlinDrinks;

    private Addon $berlinShakes;

    private Addon $munichDrinks;

    private GymScanner $dispenser;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->berlin = Gym::factory()->create(['owner_id' => $owner->id, 'name' => 'FitZone Berlin']);
        $this->munich = Gym::factory()->create(['owner_id' => $owner->id, 'name' => 'FitZone München']);

        $this->berlinDrinks = Addon::factory()->usageFlatRate()->create(['gym_id' => $this->berlin->id]);
        $this->berlinShakes = Addon::factory()->usageFlatRate()->create(['gym_id' => $this->berlin->id]);
        $this->munichDrinks = Addon::factory()->usageFlatRate()->create(['gym_id' => $this->munich->id]);

        $this->dispenser = GymScanner::create([
            'gym_id' => $this->berlin->id,
            'device_name' => 'Getränkeautomat Berlin',
            'device_task' => GymScanner::TASK_DISPENSER,
        ]);
        $this->dispenser->syncAddons([
            $this->berlinDrinks->id,
            $this->berlinShakes->id,
            $this->munichDrinks->id,
        ]);
    }

    private function memberOf(Gym $gym, Addon $addon, string $scope = MembershipPlan::SCOPE_OWN): Member
    {
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'active']);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'location_scope' => $scope]);

        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ]);
        $membership->addons()->attach($addon->id, ['mode' => 'optional']);

        return $member;
    }

    private function admitMunichInBerlin(): void
    {
        $this->berlin->update(['cross_location_checkin_rule' => Gym::CHECKIN_RULE_SELECTED]);
        $this->berlin->allowedCheckinGyms()->sync([$this->munich->id]);
    }

    private function scan(Member $member)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->dispenser->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query([
                'scan_type' => 'qr_code',
                'member_id' => $member->id,
            ]));
    }

    private function lastUsage(Member $member): ?MemberAccessLog
    {
        return MemberAccessLog::where('member_id', $member->id)
            ->forService(MemberAccessLog::SERVICE_ADDON)
            ->latest('id')
            ->first();
    }

    #[Test]
    public function any_of_the_linked_addons_unlocks_the_dispenser(): void
    {
        $member = $this->memberOf($this->berlin, $this->berlinShakes);

        $this->scan($member)
            ->assertOk()
            ->assertJsonPath('access_allowed', true);

        // Booked against the add-on actually drawn from, not the first link.
        $this->assertSame($this->berlinShakes->id, $this->lastUsage($member)->metadata['addon_id']);
    }

    #[Test]
    public function a_member_holding_none_of_the_linked_addons_is_denied(): void
    {
        $other = Addon::factory()->usageFlatRate()->create(['gym_id' => $this->berlin->id]);
        $member = $this->memberOf($this->berlin, $other);

        $this->scan($member)
            ->assertStatus(403)
            ->assertJsonPath('access_allowed', false);
    }

    #[Test]
    public function a_visiting_member_draws_on_the_package_booked_at_home(): void
    {
        $this->admitMunichInBerlin();
        $member = $this->memberOf($this->munich, $this->munichDrinks, MembershipPlan::SCOPE_ALL);

        $this->scan($member)
            ->assertOk()
            ->assertJsonPath('access_allowed', true);

        $this->assertSame($this->munichDrinks->id, $this->lastUsage($member)->metadata['addon_id']);
    }

    #[Test]
    public function a_visiting_member_is_denied_once_the_location_no_longer_admits_them(): void
    {
        // The link stays, but the operator switched Berlin back to 'own'.
        $member = $this->memberOf($this->munich, $this->munichDrinks, MembershipPlan::SCOPE_ALL);

        $this->scan($member)->assertStatus(403);
    }

    #[Test]
    public function a_visiting_member_is_denied_when_the_contract_does_not_cover_the_location(): void
    {
        $this->admitMunichInBerlin();
        $member = $this->memberOf($this->munich, $this->munichDrinks);

        $this->scan($member)->assertStatus(403);
    }

    #[Test]
    public function a_sibling_package_does_not_serve_members_of_this_location(): void
    {
        // Only Munich members may draw on the Munich package, even if one ended
        // up on a Berlin contract.
        $member = $this->memberOf($this->berlin, $this->munichDrinks);

        $this->scan($member)
            ->assertStatus(403)
            ->assertJsonPath('access_allowed', false);
    }

    #[Test]
    public function the_debounce_covers_every_linked_addon(): void
    {
        $member = $this->memberOf($this->berlin, $this->berlinShakes);

        $this->scan($member)->assertOk();
        $this->scan($member)
            ->assertOk()
            ->assertJsonPath('message', 'Zugang bereits gewährt');

        $this->assertSame(
            1,
            MemberAccessLog::where('member_id', $member->id)
                ->forService(MemberAccessLog::SERVICE_ADDON)
                ->count()
        );
    }

    #[Test]
    public function a_device_linked_before_the_pivot_existed_still_works(): void
    {
        $legacy = GymScanner::create([
            'gym_id' => $this->berlin->id,
            'device_name' => 'Alter Automat',
            'device_task' => GymScanner::TASK_DISPENSER,
            'addon_id' => $this->berlinDrinks->id,
        ]);
        $member = $this->memberOf($this->berlin, $this->berlinDrinks);

        $this->withHeader('Authorization', 'Bearer '.$legacy->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query([
                'scan_type' => 'qr_code',
                'member_id' => $member->id,
            ]))
            ->assertOk()
            ->assertJsonPath('access_allowed', true);
    }
}
