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
                'pause_start_date' => '2026-09-08',
                'pause_end_date' => '2026-10-08',
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
    public function pausing_a_membership_from_today_extends_the_end_date(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $response = $this->pause($owner, $member, $membership, ['reason' => 'Auslandsaufenthalt']);

        $response->assertSessionHasNoErrors();

        $membership->refresh();

        $this->assertSame('paused', $membership->status);
        $this->assertSame('2026-09-08', $membership->pause_start_date->format('Y-m-d'));
        $this->assertSame('2026-10-08', $membership->pause_end_date->format('Y-m-d'));

        // Exactly one month of pause, so the contract runs one month longer
        $this->assertSame('2027-01-31', $membership->end_date->format('Y-m-d'));
        $this->assertStringContainsString('Auslandsaufenthalt', (string) $membership->notes);
    }

    #[Test]
    public function a_pause_starting_in_the_future_leaves_the_membership_active(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $response = $this->pause($owner, $member, $membership, [
            'pause_start_date' => '2026-10-01',
            'pause_end_date' => '2026-10-31',
            'reason' => 'Auslandsaufenthalt',
        ]);

        $response->assertSessionHasNoErrors();

        $membership->refresh();

        // The scheduler switches the status on the start date
        $this->assertSame('active', $membership->status);
        $this->assertSame('2026-10-01', $membership->pause_start_date->format('Y-m-d'));
        $this->assertSame('2026-10-31', $membership->pause_end_date->format('Y-m-d'));

        // The end date is extended right away, by one full month
        $this->assertSame('2027-01-31', $membership->end_date->format('Y-m-d'));
        $this->assertStringContainsString('Pausierung ab 01.10.2026 eingeplant', (string) $membership->notes);
    }

    #[Test]
    public function the_status_updater_pauses_a_scheduled_membership_on_the_start_date(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $this->pause($owner, $member, $membership, [
            'pause_start_date' => '2026-10-01',
            'pause_end_date' => '2026-10-31',
        ])->assertSessionHasNoErrors();

        $this->assertSame('active', $membership->refresh()->status);

        Carbon::setTestNow('2026-10-01 03:00:00');

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $this->assertSame('paused', $membership->refresh()->status);
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
    public function a_pause_shorter_than_a_month_still_extends_by_a_full_month(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $this->pause($owner, $member, $membership, [
            'pause_start_date' => '2026-09-08',
            'pause_end_date' => '2026-09-18',
        ])->assertSessionHasNoErrors();

        // Ten days are a started month, so a full month is credited
        $this->assertSame('2027-01-31', $membership->refresh()->end_date->format('Y-m-d'));
    }

    #[Test]
    public function six_weeks_of_pause_extend_the_contract_by_two_months(): void
    {
        [$owner, $member, $membership] = $this->makeMembership();

        $this->pause($owner, $member, $membership, [
            'pause_start_date' => '2026-09-08',
            'pause_end_date' => '2026-10-20',
        ])->assertSessionHasNoErrors();

        // One full month plus a started second one
        $this->assertSame('2027-02-28', $membership->refresh()->end_date->format('Y-m-d'));
    }

    #[Test]
    public function a_contract_ending_on_the_last_day_stays_on_a_month_end(): void
    {
        [$owner, $member, $membership] = $this->makeMembership(['end_date' => '2026-12-31']);

        $this->pause($owner, $member, $membership, [
            'pause_start_date' => '2026-09-08',
            'pause_end_date' => '2026-10-20',
        ])->assertSessionHasNoErrors();

        // Two months on from 31.12. clamps to the end of February, it must not
        // spill over into March
        $this->assertSame('2027-02-28', $membership->refresh()->end_date->format('Y-m-d'));
    }

    #[Test]
    public function resuming_clears_the_pause_period(): void
    {
        [$owner, $member, $membership] = $this->makeMembership([
            'status' => 'paused',
            'pause_start_date' => '2026-09-01',
            'pause_end_date' => '2026-11-15',
            // Already extended by the credited pause months
            'end_date' => '2027-03-31',
        ]);

        $response = $this->resume($owner, $member, $membership);

        $response->assertSessionHasNoErrors();

        $membership->refresh();

        $this->assertSame('active', $membership->status);
        $this->assertNull($membership->pause_start_date);
        $this->assertNull($membership->pause_end_date);
        $this->assertStringContainsString('Wieder aufgenommen am 08.09.2026', (string) $membership->notes);
    }

    #[Test]
    public function resuming_early_keeps_the_credited_pause_months(): void
    {
        [$owner, $member, $membership] = $this->makeMembership([
            'status' => 'paused',
            'pause_start_date' => '2026-09-01',
            'pause_end_date' => '2026-11-15',
            // Three months were credited when the pause started
            'end_date' => '2027-03-31',
        ]);

        $this->resume($owner, $member, $membership)->assertSessionHasNoErrors();

        // Resuming early does not take the credited months back
        $this->assertSame('2027-03-31', $membership->refresh()->end_date->format('Y-m-d'));
    }

    #[Test]
    public function a_resumed_membership_is_not_paused_again_by_the_status_updater(): void
    {
        [$owner, $member, $membership] = $this->makeMembership([
            'status' => 'paused',
            'pause_start_date' => '2026-09-01',
            'pause_end_date' => '2026-11-15',
            'end_date' => '2027-03-31',
        ]);

        $this->resume($owner, $member, $membership)->assertSessionHasNoErrors();

        // Without the cleared dates the updater would pause it right back
        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $this->assertSame('active', $membership->refresh()->status);
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
