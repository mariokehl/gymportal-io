<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\GymScanner;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\ScannerAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The live log payload for cross-location entries.
 *
 * A visiting member's profile and contract are managed in their own
 * organisation, so the log must not offer links that would only 403 —
 * those entries go through the "Standort wechseln" dialog instead.
 */
class CrossLocationLogPayloadTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $berlin;

    private Gym $hamburg;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;

        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->berlin = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Berlin']);
        $this->hamburg = Gym::factory()->create(['owner_id' => $this->owner->id, 'name' => 'FitZone Hamburg']);

        $this->owner->update(['current_gym_id' => $this->berlin->id]);
        $this->owner = $this->owner->fresh();

        GymScanner::create([
            'gym_id' => $this->berlin->id,
            'device_number' => '1001',
            'device_name' => 'Eingang',
            'device_task' => GymScanner::TASK_CHECKIN,
        ]);
    }

    private function logEntry(Member $member, array $attributes = []): ScannerAccessLog
    {
        return ScannerAccessLog::create(array_merge([
            'gym_id' => $this->berlin->id,
            'device_number' => '1001',
            'member_id' => $member->id,
            'home_gym_id' => $member->gym_id,
            'scan_type' => ScannerAccessLog::SCAN_TYPE_QR,
            'access_granted' => true,
        ], $attributes));
    }

    private function firstLog(): array
    {
        $response = $this->actingAs($this->owner)->get(route('access-control.index'));
        $response->assertOk();

        return $response->viewData('page')['props']['recentLogs'][0];
    }

    #[Test]
    public function an_own_member_keeps_a_direct_profile_link(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);
        $this->logEntry($member);

        $log = $this->firstLog();

        $this->assertFalse($log['is_cross_location']);
        $this->assertSame(route('members.show', $member->id), $log['member_url']);
    }

    #[Test]
    public function a_visiting_member_gets_no_profile_link(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->hamburg->id]);
        $this->logEntry($member);

        $log = $this->firstLog();

        $this->assertTrue($log['is_cross_location']);
        // MemberPolicy::view would reject it, so the dialog handles this case.
        $this->assertNull($log['member_url']);
        $this->assertSame('FitZone Hamburg', $log['home_gym_name']);
        $this->assertSame($this->hamburg->id, $log['home_gym_id']);
    }

    #[Test]
    public function a_cross_location_contract_denial_names_the_plan(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->hamburg->id]);
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->hamburg->id,
            'name' => 'Basic',
        ]);
        Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        $this->logEntry($member, [
            'access_granted' => false,
            'denial_reason' => 'Vertrag „Basic“ gilt nicht für diesen Standort.',
            'metadata' => ['denial_kind' => ScannerAccessLog::DENIAL_KIND_CONTRACT],
        ]);

        $log = $this->firstLog();

        $this->assertSame(ScannerAccessLog::DENIAL_KIND_CONTRACT, $log['denial_kind']);
        $this->assertSame($plan->id, $log['contract_id']);
        $this->assertSame('Basic', $log['contract_name']);
    }

    #[Test]
    public function a_location_denial_carries_no_contract(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->hamburg->id]);

        $this->logEntry($member, [
            'access_granted' => false,
            'denial_reason' => 'Standort FitZone Hamburg ist hier nicht freigegeben.',
            'metadata' => ['denial_kind' => ScannerAccessLog::DENIAL_KIND_LOCATION],
        ]);

        $log = $this->firstLog();

        $this->assertSame(ScannerAccessLog::DENIAL_KIND_LOCATION, $log['denial_kind']);
        // Nothing to open — the fix is the location rule, not a contract.
        $this->assertNull($log['contract_id']);
    }

    #[Test]
    public function the_origin_filter_narrows_the_log_to_visitors(): void
    {
        $own = Member::factory()->create(['gym_id' => $this->berlin->id]);
        $visiting = Member::factory()->create(['gym_id' => $this->hamburg->id]);

        $this->logEntry($own);
        $this->logEntry($visiting);

        $response = $this->actingAs($this->owner)
            ->getJson(route('access-control.logs', ['origin' => 'guest']));

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(1, $data);
        // member_id is a string column on scanner_access_logs.
        $this->assertEquals($visiting->id, $data[0]['member_id']);
    }

    #[Test]
    public function the_origin_filter_counts_legacy_rows_as_own_members(): void
    {
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);

        // Written before cross-location check-in existed: no home_gym_id.
        $this->logEntry($member, ['home_gym_id' => null]);

        $response = $this->actingAs($this->owner)
            ->getJson(route('access-control.logs', ['origin' => 'home']));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
