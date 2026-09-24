<?php

namespace Tests\Feature\Console;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers that the daily membership processes skip gyms whose trial has
 * expired without an active subscription, while gyms in their trial or with
 * an active subscription keep being processed.
 */
class SkipGymsWithoutActiveAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 03:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function expiredGym(): Gym
    {
        return Gym::factory()->withoutActiveSubscription()->create();
    }

    private function trialGym(): Gym
    {
        return Gym::factory()->create([
            'subscription_status' => 'inactive',
            'subscription_ends_at' => null,
            'trial_ends_at' => now()->addDays(10),
        ]);
    }

    /**
     * @return array{0: Membership, 1: Member}
     */
    private function membershipFor(Gym $gym, array $attributes = []): array
    {
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'active']);
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'price' => 49.99,
            'billing_cycle' => 'monthly',
            'is_free_trial_plan' => false,
        ]);

        PaymentMethod::create([
            'member_id' => $member->id,
            'type' => 'sepa_direct_debit',
            'status' => 'active',
            'is_default' => true,
            'iban' => 'DE02120300000000202051',
            'account_holder' => 'Test Member',
            'sepa_mandate_status' => 'active',
            'sepa_mandate_reference' => 'MANDATE-'.$member->id,
            'sepa_mandate_signed_at' => now()->subYear(),
            'sepa_mandate_acknowledged' => true,
        ]);

        $membership = Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => 'active',
        ], $attributes));

        return [$membership, $member];
    }

    private function duePayment(Gym $gym, Member $member, Membership $membership): Payment
    {
        return Payment::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'amount' => 49.99,
            'currency' => 'EUR',
            'status' => 'pending',
            'due_date' => '2026-09-24',
            'payment_method' => 'sepa_direct_debit',
            'description' => 'Mitgliedsbeitrag September',
        ]);
    }

    #[Test]
    public function status_updates_skip_gyms_with_an_expired_trial(): void
    {
        [$skipped] = $this->membershipFor($this->expiredGym(), ['cancellation_date' => '2026-09-01']);
        [$processed] = $this->membershipFor($this->trialGym(), ['cancellation_date' => '2026-09-01']);

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $this->assertSame('active', $skipped->fresh()->status);
        $this->assertSame('cancelled', $processed->fresh()->status);
    }

    #[Test]
    public function members_of_gyms_with_an_expired_trial_are_not_deactivated(): void
    {
        $skipped = Member::factory()->create(['gym_id' => $this->expiredGym()->id, 'status' => 'active']);
        $processed = Member::factory()->create(['gym_id' => $this->trialGym()->id, 'status' => 'active']);

        $this->artisan('memberships:update-statuses')->assertExitCode(0);

        $this->assertSame('active', $skipped->fresh()->status);
        $this->assertSame('inactive', $processed->fresh()->status);
    }

    #[Test]
    public function due_payments_are_only_processed_for_gyms_with_active_access(): void
    {
        // A payment due inside a pause is canceled once it is processed, which
        // makes the processing visible without collecting over a payment method
        $pause = [
            'status' => 'paused',
            'pause_start_date' => '2026-09-01',
            'pause_end_date' => '2026-09-30',
        ];

        $expiredGym = $this->expiredGym();
        [$membership, $member] = $this->membershipFor($expiredGym, $pause);
        $skipped = $this->duePayment($expiredGym, $member, $membership);

        $activeGym = Gym::factory()->create();
        [$membership, $member] = $this->membershipFor($activeGym, $pause);
        $processed = $this->duePayment($activeGym, $member, $membership);

        $this->artisan('memberships:process-payments', ['--days' => 0])->assertSuccessful();

        $this->assertSame('pending', $skipped->fresh()->status);
        $this->assertSame('canceled', $processed->fresh()->status);
    }

    #[Test]
    public function no_upcoming_payments_are_created_for_gyms_with_an_expired_trial(): void
    {
        [$skipped] = $this->membershipFor($this->expiredGym(), ['start_date' => '2026-09-24']);
        [$processed] = $this->membershipFor($this->trialGym(), ['start_date' => '2026-09-24']);

        $this->artisan('memberships:process-payments', ['--days' => 0])->assertSuccessful();

        $this->assertSame(0, $skipped->payments()->count());
        $this->assertSame(1, $processed->payments()->count());
    }

    #[Test]
    public function the_daily_process_reports_the_skipped_gyms(): void
    {
        $gym = $this->expiredGym();

        $this->artisan('memberships:daily-process', ['--skip-status' => true, '--skip-payments' => true])
            ->expectsOutputToContain("#{$gym->id} {$gym->name}")
            ->assertExitCode(0);
    }
}
