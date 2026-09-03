<?php

namespace Tests\Feature\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Services\SchedulerHealthCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SchedulerHealthCheckFreeTrialTest extends TestCase
{
    use RefreshDatabase;

    private function activeMembership(bool $isFreeTrialPlan, bool $withPaymentMethod): Membership
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'is_free_trial_plan' => $isFreeTrialPlan,
        ]);

        if ($withPaymentMethod) {
            PaymentMethod::create([
                'member_id' => $member->id,
                'type' => 'sepa',
                'status' => 'active',
            ]);
        }

        return Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    /**
     * Captures the count the health check reports for memberships that lack a
     * payment method, or null when it stayed silent.
     */
    private function reportedMissingPaymentCount(): ?int
    {
        $count = null;

        Log::shouldReceive('warning')
            ->andReturnUsing(function (string $message) use (&$count) {
                if (preg_match('/Found (\d+) active memberships without payment methods/', $message, $matches)) {
                    $count = (int) $matches[1];
                }
            });
        Log::shouldReceive('error')->andReturnNull();

        app(SchedulerHealthCheckService::class)->performHealthCheck();

        return $count;
    }

    public function test_a_paid_membership_without_payment_method_is_reported(): void
    {
        $this->activeMembership(isFreeTrialPlan: false, withPaymentMethod: false);

        $this->assertSame(1, $this->reportedMissingPaymentCount());
    }

    public function test_a_free_trial_membership_without_payment_method_is_ignored(): void
    {
        $this->activeMembership(isFreeTrialPlan: true, withPaymentMethod: false);

        $this->assertNull($this->reportedMissingPaymentCount());
    }

    public function test_a_paid_membership_with_an_active_payment_method_is_ignored(): void
    {
        $this->activeMembership(isFreeTrialPlan: false, withPaymentMethod: true);

        $this->assertNull($this->reportedMissingPaymentCount());
    }

    public function test_only_the_paid_membership_is_counted_when_both_exist(): void
    {
        $this->activeMembership(isFreeTrialPlan: true, withPaymentMethod: false);
        $this->activeMembership(isFreeTrialPlan: false, withPaymentMethod: false);

        $this->assertSame(1, $this->reportedMissingPaymentCount());
    }
}
