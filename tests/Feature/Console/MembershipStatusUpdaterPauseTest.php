<?php

namespace Tests\Feature\Console;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers how memberships:update-statuses moves memberships in and out of the
 * paused state. Once a pause is over the period is cleared, so the membership
 * is not picked up again on every following run.
 */
class MembershipStatusUpdaterPauseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-08 03:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeMembership(array $attributes = []): Membership
    {
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        return Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
        ], $attributes));
    }

    #[Test]
    public function an_expired_pause_reactivates_the_membership_and_clears_the_period(): void
    {
        $membership = $this->makeMembership([
            'status' => 'paused',
            'pause_start_date' => '2026-08-01',
            'pause_end_date' => '2026-09-01',
        ]);

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $membership->refresh();

        $this->assertSame('active', $membership->status);
        $this->assertNull($membership->pause_start_date);
        $this->assertNull($membership->pause_end_date);
    }

    #[Test]
    public function a_reactivated_membership_is_not_picked_up_again_on_the_next_run(): void
    {
        $membership = $this->makeMembership([
            'status' => 'paused',
            'pause_start_date' => '2026-08-01',
            'pause_end_date' => '2026-09-01',
        ]);

        $this->artisan('memberships:update-statuses')
            ->expectsOutputToContain('1 pausierte Mitgliedschaft(en) wurden wieder aktiviert.')
            ->assertExitCode(0);

        // The cleared period keeps it out of the query on the second run
        $this->artisan('memberships:update-statuses')
            ->doesntExpectOutputToContain('1 pausierte Mitgliedschaft(en) wurden wieder aktiviert.')
            ->assertExitCode(0);

        $this->assertSame('active', $membership->refresh()->status);
    }

    #[Test]
    public function a_running_pause_keeps_its_period(): void
    {
        $membership = $this->makeMembership([
            'status' => 'paused',
            'pause_start_date' => '2026-09-01',
            'pause_end_date' => '2026-10-01',
        ]);

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $membership->refresh();

        $this->assertSame('paused', $membership->status);
        $this->assertSame('2026-09-01', $membership->pause_start_date->format('Y-m-d'));
        $this->assertSame('2026-10-01', $membership->pause_end_date->format('Y-m-d'));
    }

    #[Test]
    public function a_pause_reaching_its_start_date_pauses_the_membership(): void
    {
        $membership = $this->makeMembership([
            'status' => 'active',
            'pause_start_date' => '2026-09-08',
            'pause_end_date' => '2026-10-08',
        ]);

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $membership->refresh();

        $this->assertSame('paused', $membership->status);
        $this->assertSame('2026-09-08', $membership->pause_start_date->format('Y-m-d'));
    }

    #[Test]
    public function a_membership_without_a_pause_period_is_left_alone(): void
    {
        $membership = $this->makeMembership([
            'status' => 'active',
            'pause_start_date' => null,
            'pause_end_date' => null,
        ]);

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $this->assertSame('active', $membership->refresh()->status);
    }
}
