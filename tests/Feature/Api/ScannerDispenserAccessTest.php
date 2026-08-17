<?php

namespace Tests\Feature\Api;

use App\Models\Addon;
use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScannerDispenserAccessTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Addon $drinkPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->drinkPackage = Addon::factory()->usageFlatRate()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Getränke-Flatrate',
            'settled_via_device' => true,
        ]);
    }

    private function scanner(string $task, ?int $addonId = null): GymScanner
    {
        return GymScanner::create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Gerät',
            'device_task' => $task,
            'addon_id' => $addonId,
        ]);
    }

    private function member(array $attributes = []): Member
    {
        return Member::factory()->create(array_merge([
            'gym_id' => $this->gym->id,
            'status' => 'active',
        ], $attributes));
    }

    private function membershipFor(Member $member): Membership
    {
        $plan = MembershipPlan::factory()->create(['gym_id' => $this->gym->id]);

        return Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ]);
    }

    private function scan(GymScanner $scanner, Member $member)
    {
        return $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query([
                'scan_type' => 'qr_code',
                'member_id' => $member->id,
            ]));
    }

    #[Test]
    public function a_member_with_the_booked_drink_package_may_use_the_dispenser(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, ['mode' => 'optional']);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertOk()
            ->assertJsonPath('access_allowed', true);
    }

    #[Test]
    public function a_member_without_the_drink_package_is_denied_at_the_dispenser(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertStatus(403)
            ->assertJsonPath('access_allowed', false)
            ->assertJsonPath('message', 'Leistung nicht gebucht');
    }

    #[Test]
    public function the_same_member_still_gets_through_the_door(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        // No drink package booked, but the entrance does not care.
        $this->scan($this->scanner(GymScanner::TASK_CHECKIN), $member)
            ->assertOk()
            ->assertJsonPath('access_allowed', true);
    }

    #[Test]
    public function a_member_holding_a_different_addon_is_denied(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);

        $sauna = Addon::factory()->usageFlatRate()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Sauna-Paket',
        ]);
        $membership->addons()->attach($sauna->id, ['mode' => 'optional']);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Leistung nicht gebucht');
    }

    #[Test]
    public function an_included_drink_package_counts_as_booked(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, ['mode' => 'included', 'price' => 0]);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertOk()
            ->assertJsonPath('access_allowed', true);
    }

    #[Test]
    public function a_cancelled_package_stays_usable_until_the_period_ends(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, [
            'mode' => 'optional',
            'cancelled_at' => now(),
            'cancellation_effective_at' => now()->addDays(5)->toDateString(),
        ]);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertOk()
            ->assertJsonPath('access_allowed', true);
    }

    #[Test]
    public function a_cancelled_package_is_denied_once_the_period_ended(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, [
            'mode' => 'optional',
            'cancelled_at' => now()->subMonth(),
            'cancellation_effective_at' => now()->subDay()->toDateString(),
        ]);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Leistung nicht gebucht');
    }

    #[Test]
    public function a_deactivated_addon_is_denied(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, ['mode' => 'optional']);
        $this->drinkPackage->update(['is_active' => false]);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertStatus(403);
    }

    #[Test]
    public function a_guest_is_denied_at_the_dispenser(): void
    {
        $guest = $this->member(['guest_access' => true]);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $guest)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Add-ons stehen nur Mitgliedern zur Verfügung');
    }

    #[Test]
    public function a_guest_still_gets_through_the_door(): void
    {
        $guest = $this->member(['guest_access' => true]);

        $this->scan($this->scanner(GymScanner::TASK_CHECKIN), $guest)
            ->assertOk()
            ->assertJsonPath('access_allowed', true);
    }

    #[Test]
    public function a_member_without_a_membership_is_denied_everywhere(): void
    {
        $member = $this->member();

        $this->scan($this->scanner(GymScanner::TASK_CHECKIN), $member)
            ->assertStatus(403);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertStatus(403);
    }

    #[Test]
    public function a_dispenser_without_a_linked_addon_denies_access(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, ['mode' => 'optional']);

        // Misconfigured device: fail closed rather than release the tap.
        $this->scan($this->scanner(GymScanner::TASK_DISPENSER), $member)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Gerät ist mit keiner Leistung verknüpft');
    }

    #[Test]
    public function a_dispenser_scan_does_not_create_a_check_in(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, ['mode' => 'optional']);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertOk();

        $this->assertSame(0, CheckIn::where('member_id', $member->id)->count());
    }

    #[Test]
    public function the_recent_check_in_shortcut_does_not_bypass_the_addon_check(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        // Member just walked through the door, but booked no drink package.
        CheckIn::create([
            'member_id' => $member->id,
            'gym_id' => $this->gym->id,
            'check_in_time' => now(),
            'check_in_method' => 'qr_code',
        ]);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Leistung nicht gebucht');
    }

    #[Test]
    public function an_addon_of_another_membership_does_not_count(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        // Booking sits on a different member's membership.
        $other = $this->member();
        $otherMembership = $this->membershipFor($other);
        $otherMembership->addons()->attach($this->drinkPackage->id, ['mode' => 'optional']);

        $this->scan($this->scanner(GymScanner::TASK_DISPENSER, $this->drinkPackage->id), $member)
            ->assertStatus(403);
    }
}
