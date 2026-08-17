<?php

namespace Tests\Feature\Api;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\MemberAccessLog;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScannerAddonUsageLogTest extends TestCase
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

    private function dispenser(?int $addonId = null): GymScanner
    {
        return GymScanner::create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Getränkeautomat Theke',
            'device_task' => GymScanner::TASK_DISPENSER,
            'addon_id' => $addonId ?? $this->drinkPackage->id,
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

    private function scan(GymScanner $scanner, Member $member, string $scanType = 'qr_code')
    {
        $params = ['scan_type' => $scanType, 'member_id' => $member->id];

        return $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query($params));
    }

    #[Test]
    public function a_successful_draw_is_logged(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, ['mode' => 'optional']);

        $this->scan($this->dispenser(), $member)->assertOk();

        $log = MemberAccessLog::where('member_id', $member->id)->sole();

        $this->assertSame(MemberAccessLog::SERVICE_ADDON, $log->service);
        $this->assertSame(MemberAccessLog::ACTION_ACCESS_ATTEMPT, $log->action);
        $this->assertSame(MemberAccessLog::METHOD_QR, $log->method);
        $this->assertTrue($log->success);
        $this->assertNotNull($log->accessed_at, 'The history renders accessed_at.');
        $this->assertSame($this->drinkPackage->id, $log->metadata['addon_id']);
        $this->assertSame('Getränke-Flatrate', $log->metadata['addon_name']);
        $this->assertSame('Getränkeautomat Theke', $log->metadata['device_name']);
    }

    #[Test]
    public function a_denied_draw_is_logged_with_its_reason(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $this->scan($this->dispenser(), $member)->assertStatus(403);

        $log = MemberAccessLog::where('member_id', $member->id)->sole();

        $this->assertFalse($log->success);
        $this->assertSame('Leistung nicht gebucht', $log->metadata['reason']);
    }

    #[Test]
    public function a_guest_refused_at_the_dispenser_is_logged(): void
    {
        $guest = $this->member(['guest_access' => true]);

        $this->scan($this->dispenser(), $guest)->assertStatus(403);

        $log = MemberAccessLog::where('member_id', $guest->id)->sole();

        $this->assertFalse($log->success);
        $this->assertSame(
            'Add-ons stehen nur Mitgliedern zur Verfügung',
            $log->metadata['reason']
        );
    }

    #[Test]
    public function an_nfc_draw_is_logged_as_nfc(): void
    {
        $member = $this->member();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, ['mode' => 'optional']);
        $member->accessConfig()->create(['nfc_uid' => 'AABBCCDD', 'nfc_enabled' => true]);

        $scanner = $this->dispenser();
        $this->withHeader('Authorization', 'Bearer '.$scanner->getPlainTextToken())
            ->getJson('/api/scanner/verify-membership?'.http_build_query([
                'scan_type' => 'nfc_card',
                'nfc_card_id' => 'AABBCCDD',
            ]))
            ->assertOk();

        $this->assertSame(
            MemberAccessLog::METHOD_NFC,
            MemberAccessLog::where('member_id', $member->id)->sole()->method
        );
    }

    #[Test]
    public function a_door_scan_writes_no_addon_log(): void
    {
        $member = $this->member();
        $this->membershipFor($member);

        $door = GymScanner::create([
            'gym_id' => $this->gym->id,
            'device_name' => 'Haupteingang',
            'device_task' => GymScanner::TASK_CHECKIN,
        ]);

        $this->scan($door, $member)->assertOk();

        // A door scan is already covered by the check-ins.
        $this->assertSame(0, MemberAccessLog::where('member_id', $member->id)->count());
    }

    #[Test]
    public function the_service_name_shows_the_booked_addon(): void
    {
        $log = MemberAccessLog::create([
            'member_id' => $this->member()->id,
            'action' => MemberAccessLog::ACTION_ACCESS_ATTEMPT,
            'service' => MemberAccessLog::SERVICE_ADDON,
            'metadata' => ['addon_name' => 'Getränke-Flatrate'],
            'accessed_at' => now(),
        ]);

        $this->assertSame('Getränke-Flatrate', $log->service_name);
    }

    #[Test]
    public function the_service_name_falls_back_when_the_addon_is_unknown(): void
    {
        $log = MemberAccessLog::create([
            'member_id' => $this->member()->id,
            'action' => MemberAccessLog::ACTION_ACCESS_ATTEMPT,
            'service' => MemberAccessLog::SERVICE_ADDON,
            'accessed_at' => now(),
        ]);

        $this->assertSame('Zusatzleistung', $log->service_name);
    }

    /**
     * @return array{0: User, 1: Member}
     */
    private function ownerAndMember(): array
    {
        $ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $owner = User::factory()->create([
            'role_id' => $ownerRoleId,
            'current_gym_id' => $this->gym->id,
        ]);
        $this->gym->update(['owner_id' => $owner->id]);

        return [$owner->fresh(), $this->member()];
    }

    #[Test]
    public function the_member_page_shows_the_addon_usage_in_its_history(): void
    {
        [$owner, $member] = $this->ownerAndMember();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, ['mode' => 'optional']);

        $this->scan($this->dispenser(), $member)->assertOk();

        $this->actingAs($owner)
            ->get(route('members.show', $member))
            ->assertInertia(fn ($page) => $page
                ->has('member.access_logs', 1)
                ->where('member.access_logs.0.action_name', 'Zugangsversuch')
                ->where('member.access_logs.0.service_name', 'Getränke-Flatrate')
                ->where('member.access_logs.0.device_name', 'Getränkeautomat Theke')
                ->where('member.access_logs.0.method', 'QR-Code')
                ->where('member.access_logs.0.success', true)
            );
    }

    #[Test]
    public function the_history_also_lists_management_actions(): void
    {
        [$owner, $member] = $this->ownerAndMember();

        // created_at is not fillable, so set it after creation to control order.
        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_CONFIG_UPDATED,
            'performed_by' => $owner->id,
        ])->forceFill(['created_at' => now()->subMinutes(2)])->save();

        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_QR_INVALIDATED,
            'performed_by' => $owner->id,
        ])->forceFill(['created_at' => now()->subMinute()])->save();

        $this->actingAs($owner)
            ->get(route('members.show', $member))
            ->assertInertia(fn ($page) => $page
                ->has('member.access_logs', 2)
                ->where('member.access_logs.0.action_name', 'QR-Code invalidiert')
                ->where('member.access_logs.0.is_access_attempt', false)
                // Columns that only apply to a scan stay empty.
                ->where('member.access_logs.0.service_name', null)
                ->where('member.access_logs.0.method', null)
                ->where('member.access_logs.0.success', null)
                ->where('member.access_logs.1.action_name', 'Konfiguration aktualisiert')
            );
    }

    #[Test]
    public function management_actions_name_the_staff_member(): void
    {
        [$owner, $member] = $this->ownerAndMember();

        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_CONFIG_UPDATED,
            'performed_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('members.show', $member))
            ->assertInertia(fn ($page) => $page
                ->where('member.access_logs.0.performed_by_name', $owner->fullName())
            );
    }

    #[Test]
    public function scans_and_management_actions_are_ordered_together(): void
    {
        [$owner, $member] = $this->ownerAndMember();
        $membership = $this->membershipFor($member);
        $membership->addons()->attach($this->drinkPackage->id, ['mode' => 'optional']);

        // Older management action, then a scan — newest first afterwards.
        MemberAccessLog::create([
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_CONFIG_UPDATED,
            'performed_by' => $owner->id,
        ])->forceFill(['created_at' => now()->subHour()])->save();

        $this->scan($this->dispenser(), $member)->assertOk();

        $this->actingAs($owner)
            ->get(route('members.show', $member))
            ->assertInertia(fn ($page) => $page
                ->has('member.access_logs', 2)
                ->where('member.access_logs.0.action_name', 'Zugangsversuch')
                ->where('member.access_logs.1.action_name', 'Konfiguration aktualisiert')
            );
    }
}
