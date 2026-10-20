<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberCountVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $staffRoleId = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff'])->id;

        $owner = User::factory()->create(['role_id' => $ownerRoleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $owner->fresh();

        $staff = User::factory()->create(['role_id' => $staffRoleId]);
        $staff->update(['current_gym_id' => $this->gym->id]);
        $this->staff = $staff->fresh();

        Member::factory()->count(3)->create(['gym_id' => $this->gym->id, 'status' => 'active']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeMembersTile(array $props): ?array
    {
        return collect($props['stats']['main_stats'])->firstWhere('key', 'active_members');
    }

    #[Test]
    public function an_owner_sees_member_counts_on_the_dashboard(): void
    {
        $this->actingAs($this->owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $props = $page->toArray()['props'];

                $this->assertTrue($props['canViewMemberCounts']);
                $this->assertSame(3, $props['totalMembers']);
                $this->assertSame(3, $this->activeMembersTile($props)['value']);
                $this->assertArrayHasKey('detailed_stats', $props['stats']);
            });
    }

    #[Test]
    public function staff_does_not_receive_member_counts_on_the_dashboard(): void
    {
        $this->actingAs($this->staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $props = $page->toArray()['props'];

                $this->assertFalse($props['canViewMemberCounts']);
                $this->assertNull($props['totalMembers']);

                $tile = $this->activeMembersTile($props);
                $this->assertNull($tile['value']);
                $this->assertNull($tile['change']);

                foreach (['detailed_stats', 'status_distribution', 'plan_stats', 'financial_stats', 'trends'] as $key) {
                    $this->assertArrayNotHasKey($key, $props['stats']);
                }
            });
    }

    #[Test]
    public function the_member_list_flags_whether_the_total_may_be_shown(): void
    {
        $this->actingAs($this->owner)
            ->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canViewMemberCounts', true));

        $this->actingAs($this->staff)
            ->get(route('members.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canViewMemberCounts', false));
    }
}
