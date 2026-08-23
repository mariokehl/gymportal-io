<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberAccessLogHistoryTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym, 2: Member}
     */
    private function ownerWithMember(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        return [$owner->fresh(), $gym, $member];
    }

    private function createLogs(Member $member, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            MemberAccessLog::create([
                'member_id' => $member->id,
                'action' => MemberAccessLog::ACTION_ACCESS_ATTEMPT,
                'service' => MemberAccessLog::SERVICE_GYM,
                'method' => MemberAccessLog::METHOD_QR,
                'success' => true,
                'accessed_at' => now()->subMinutes($count - $i),
                'created_at' => now()->subMinutes($count - $i),
                'metadata' => ['device_name' => 'Drehkreuz '.$i],
            ]);
        }
    }

    #[Test]
    public function the_member_page_only_renders_the_first_page_of_the_access_history(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $this->createLogs($member, 12);

        $this->actingAs($owner)
            ->get(route('members.show', $member))
            ->assertInertia(fn ($page) => $page
                ->component('Members/Show')
                ->has('member.access_logs', 5)
                ->where('accessLogsTotal', 12)
            );
    }

    #[Test]
    public function it_lazy_loads_the_next_page_of_the_access_history(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $this->createLogs($member, 12);

        $response = $this->actingAs($owner)
            ->getJson(route('members.access.logs', ['member' => $member, 'page' => 2]))
            ->assertOk();

        $response->assertJsonCount(5, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('total', 12)
            ->assertJsonPath('has_more', true);

        // Same row shape the member page renders initially.
        $response->assertJsonStructure([
            'data' => [['id', 'action', 'action_name', 'is_access_attempt', 'service_name', 'method', 'success', 'accessed_at', 'device_name', 'performed_by_name']],
        ]);
        $this->assertSame('QR-Code', $response->json('data.0.method'));
    }

    #[Test]
    public function the_last_page_reports_that_no_more_entries_exist(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $this->createLogs($member, 12);

        $this->actingAs($owner)
            ->getJson(route('members.access.logs', ['member' => $member, 'page' => 3]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('has_more', false);
    }

    #[Test]
    public function it_denies_access_to_a_member_of_another_gym(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $this->createLogs($member, 3);

        $otherOwner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $otherGym = Gym::factory()->create(['owner_id' => $otherOwner->id]);
        $otherOwner->update(['current_gym_id' => $otherGym->id]);

        $this->actingAs($otherOwner->fresh())
            ->getJson(route('members.access.logs', $member))
            ->assertForbidden();
    }
}
