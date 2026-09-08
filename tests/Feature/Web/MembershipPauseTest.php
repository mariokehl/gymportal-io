<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pausing a membership must not shorten the contract term: the end date moves
 * back by the pause duration, and resuming early gives the unused days back.
 */
class MembershipPauseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Role::factory()->create(['name' => 'Administrator', 'slug' => 'admin']);
        Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);

        Carbon::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Member, 2: Membership}
     */
    private function makeMembership(array $attributes = []): array
    {
        $owner = User::factory()->create();
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $membership = Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $attributes));

        return [$owner->fresh(), $member, $membership];
    }

    private function pause(User $owner, Member $member, Membership $membership, array $payload = [])
    {
        return $this->actingAs($owner)->put(
            route('members.memberships.pause', [
                'member' => $member->id,
                'membership' => $membership->id,
            ]),
            array_merge([
                'pause_start_date' => '2026-10-01',
                'pause_end_date' => '2026-10-31',
            ], $payload),
        );
    }

    private function resume(User $owner, Member $member, Membership $membership)
    {
        return $this->actingAs($owner)->put(
            route('members.memberships.resume', [
                'member' => $member->id,
                'membership' => $membership->id,
            ]),
            [],
        );
    }

    #[Test]
    public function pausing_a_membership_extends_the_end_date_by_the_pause_duration(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $response = $this->pause($owner, $member, $membership, ['reason' => 'Auslandsaufenthalt']);

        $response->assertSessionHasNoErrors();

        $membership->refresh();

        $this->assertSame('paused', $membership->status);
        $this->assertSame('2026-10-01', $membership->pause_start_date->format('Y-m-d'));
        $this->assertSame('2026-10-31', $membership->pause_end_date->format('Y-m-d'));

        // 30 days between the two dates, so the contract runs 30 days longer
        $this->assertSame('2027-01-30', $membership->end_date->format('Y-m-d'));
        $this->assertStringContainsString('Auslandsaufenthalt', (string) $membership->notes);
    }

    #[Test]
    public function pausing_a_membership_without_an_end_date_keeps_it_open(): void
    {
        [$owner, $member, $membership] = $this->makeMembership(['end_date' => null]);

        $this->pause($owner, $member, $membership)->assertSessionHasNoErrors();

        $membership->refresh();

        $this->assertSame('paused', $membership->status);
        $this->assertNull($membership->end_date);
    }

    #[Test]
    public function only_active_memberships_can_be_paused(): void
    {
        [$owner, $member, $membership] = $this->makeMembership(['status' => 'cancelled']);

        $this->pause($owner, $member, $membership)->assertSessionHasErrors('status');

        $membership->refresh();

        $this->assertSame('cancelled', $membership->status);
        $this->assertSame('2026-12-31', $membership->end_date->format('Y-m-d'));
    }

    #[Test]
    public function resuming_early_gives_the_unused_pause_days_back(): void
    {
        [$owner, $member, $membership] = $this->makeMembership([
            'status' => 'paused',
            'pause_start_date' => '2026-09-01',
            'pause_end_date' => '2026-10-01',
            // Already extended by the 30 pause days when the pause started
            'end_date' => '2027-01-30',
        ]);

        $response = $this->resume($owner, $member, $membership);

        $response->assertSessionHasNoErrors();

        $membership->refresh();

        $this->assertSame('active', $membership->status);
        $this->assertSame('2026-09-08', $membership->pause_end_date->format('Y-m-d'));

        // Resumed 23 days early, so those days are removed again
        $this->assertSame('2027-01-07', $membership->end_date->format('Y-m-d'));
        $this->assertStringContainsString('Wieder aufgenommen am 08.09.2026', (string) $membership->notes);
    }

    #[Test]
    public function resuming_after_the_scheduled_pause_end_keeps_the_end_date(): void
    {
        [$owner, $member, $membership] = $this->makeMembership([
            'status' => 'paused',
            'pause_start_date' => '2026-08-01',
            'pause_end_date' => '2026-08-31',
            'end_date' => '2027-01-30',
        ]);

        $this->resume($owner, $member, $membership)->assertSessionHasNoErrors();

        $membership->refresh();

        $this->assertSame('active', $membership->status);
        $this->assertSame('2027-01-30', $membership->end_date->format('Y-m-d'));
    }

    #[Test]
    public function only_paused_memberships_can_be_resumed(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $this->resume($owner, $member, $membership)->assertSessionHasErrors('status');

        $this->assertSame('active', $membership->refresh()->status);
    }

    #[Test]
    public function a_membership_of_another_member_cannot_be_paused(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $otherMember = Member::factory()->create(['gym_id' => $member->gym_id]);

        $this->actingAs($owner)->put(
            route('members.memberships.pause', [
                'member' => $otherMember->id,
                'membership' => $membership->id,
            ]),
            [
                'pause_start_date' => '2026-10-01',
                'pause_end_date' => '2026-10-31',
            ],
        )->assertForbidden();

        $this->assertSame('active', $membership->refresh()->status);
    }
}
