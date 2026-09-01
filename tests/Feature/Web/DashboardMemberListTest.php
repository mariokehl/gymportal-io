<?php

namespace Tests\Feature\Web;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardMemberListTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym}
     */
    private function ownerWithGym(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return [$owner->fresh(), $gym];
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardMember(User $owner, Member $member): array
    {
        $response = $this->actingAs($owner)->get(route('dashboard'));
        $response->assertOk();

        $row = null;
        $response->assertInertia(function (AssertableInertia $page) use ($member, &$row) {
            $row = collect($page->toArray()['props']['members'])
                ->firstWhere('id', $member->id);
        });

        $this->assertNotNull($row, 'Member is missing from the dashboard list.');

        return $row;
    }

    #[Test]
    public function it_exposes_the_last_check_in_for_members_without_an_active_membership(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $checkIn = CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subDays(3),
        ]);

        $row = $this->dashboardMember($owner, $member);

        $this->assertSame('❌', $row['membership']);
        $this->assertNotNull($row['last_check_in']);
        $this->assertSame(
            $checkIn->check_in_time->toISOString(),
            Member::find($member->id)->last_check_in->check_in_time->toISOString()
        );
    }

    #[Test]
    public function it_exposes_the_last_check_in_for_members_with_an_active_membership(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'name' => 'Premium']);
        Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
        ]);
        CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subDays(3),
        ]);

        $row = $this->dashboardMember($owner, $member);

        $this->assertSame('Premium', $row['membership']);
        $this->assertNotNull($row['last_check_in']);
    }

    #[Test]
    public function it_reports_no_last_check_in_when_the_member_never_visited(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $row = $this->dashboardMember($owner, $member);

        $this->assertArrayHasKey('last_check_in', $row);
        $this->assertNull($row['last_check_in']);
    }
}
