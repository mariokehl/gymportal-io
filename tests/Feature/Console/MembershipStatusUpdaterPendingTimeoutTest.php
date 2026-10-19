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
 * Covers the timeout after which memberships:update-statuses cancels a
 * membership that was never activated. Pre-registrations for a future opening
 * must not run into it before their contract has even started.
 */
class MembershipStatusUpdaterPendingTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 03:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function pendingMembership(string $createdAt, string $startDate): Membership
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'pending']);

        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => MembershipPlan::factory()->create(['gym_id' => $gym->id])->id,
            'start_date' => $startDate,
            'end_date' => null,
            'status' => 'pending',
        ]);

        // created_at is not fillable, so it is backdated directly
        $membership->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $membership;
    }

    #[Test]
    public function a_long_pending_membership_that_has_started_is_cancelled(): void
    {
        $membership = $this->pendingMembership('2026-08-01 12:00:00', '2026-08-01');

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $membership->refresh();
        $this->assertSame('expired', $membership->status);
        $this->assertSame(Membership::PENDING_TIMEOUT_REASON, $membership->cancellation_reason);
    }

    #[Test]
    public function a_pre_registration_for_a_future_opening_stays_pending(): void
    {
        $membership = $this->pendingMembership('2026-07-01 12:00:00', '2026-11-01');

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $this->assertSame('pending', $membership->refresh()->status);
    }

    #[Test]
    public function the_timeout_counts_from_the_contract_start_once_it_has_passed(): void
    {
        // Created long ago, but the contract only started 14 days ago
        $membership = $this->pendingMembership('2026-07-01 12:00:00', '2026-10-01');

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $this->assertSame('pending', $membership->refresh()->status);
    }
}
