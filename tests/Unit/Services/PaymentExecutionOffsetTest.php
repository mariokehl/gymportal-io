<?php

namespace Tests\Unit\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers that the gym specific execution date settings override the system
 * defaults used by PaymentService for initial and recurring payments.
 */
class PaymentExecutionOffsetTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaymentService::class);
        Carbon::setTestNow('2026-06-25');
    }

    private function makeMembership(Gym $gym, Member $member): Membership
    {
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'billing_cycle' => 'monthly',
            'trial_period_days' => 0,
        ]);

        return Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'pending',
        ]);
    }

    private function makePaymentMethod(Member $member, string $type): PaymentMethod
    {
        return PaymentMethod::create([
            'member_id' => $member->id,
            'type' => $type,
            'status' => 'active',
            'is_default' => true,
        ]);
    }

    public function test_initial_payment_uses_the_system_default_without_an_override(): void
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $membership = $this->makeMembership($gym, $member);
        $method = $this->makePaymentMethod($member, 'sepa_direct_debit');

        $payment = $this->service->createPendingPayment($member, $membership, $method);

        // System default for sepa_direct_debit: 3 days after the due date.
        $this->assertSame(
            Carbon::today()->addDays(3)->toDateString(),
            $payment->execution_date->toDateString()
        );
    }

    public function test_initial_payment_uses_the_gym_specific_override(): void
    {
        $gym = Gym::factory()->create();
        $gym->setPaymentExecutionOffsets('sepa_direct_debit', 10, -1);

        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $membership = $this->makeMembership($gym, $member);
        $method = $this->makePaymentMethod($member, 'sepa_direct_debit');

        $payment = $this->service->createPendingPayment($member, $membership, $method);

        $this->assertSame(
            Carbon::today()->addDays(10)->toDateString(),
            $payment->execution_date->toDateString()
        );
    }

    public function test_recurring_payments_use_the_system_default_without_an_override(): void
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $membership = $this->makeMembership($gym, $member);
        $this->makePaymentMethod($member, 'banktransfer');

        $payments = $this->service->createRecurringPayments($member->fresh(), $membership, 1);

        // System default for banktransfer: 5 days before the due date.
        $this->assertSame(
            Carbon::today()->subDays(5)->toDateString(),
            $payments[0]->execution_date->toDateString()
        );
    }

    public function test_recurring_payments_use_the_gym_specific_override(): void
    {
        $gym = Gym::factory()->create();
        $gym->setPaymentExecutionOffsets('banktransfer', 7, -9);

        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $membership = $this->makeMembership($gym, $member);
        $this->makePaymentMethod($member, 'banktransfer');

        $payments = $this->service->createRecurringPayments($member->fresh(), $membership, 1);

        $this->assertSame(
            Carbon::today()->subDays(9)->toDateString(),
            $payments[0]->execution_date->toDateString()
        );
    }

    public function test_an_override_only_applies_to_its_own_gym(): void
    {
        $configuredGym = Gym::factory()->create();
        $configuredGym->setPaymentExecutionOffsets('cash', 12, 0);

        $otherGym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $otherGym->id]);
        $membership = $this->makeMembership($otherGym, $member);
        $method = $this->makePaymentMethod($member, 'cash');

        $payment = $this->service->createPendingPayment($member, $membership, $method);

        // Untouched gym keeps the system default for cash: same day.
        $this->assertSame(
            Carbon::today()->toDateString(),
            $payment->execution_date->toDateString()
        );
    }

    public function test_resetting_an_override_restores_the_system_default(): void
    {
        $gym = Gym::factory()->create();
        $gym->setPaymentExecutionOffsets('invoice', 25, -4);
        $gym->resetPaymentExecutionOffsets('invoice');

        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $membership = $this->makeMembership($gym, $member);
        $method = $this->makePaymentMethod($member, 'invoice');

        $payment = $this->service->createPendingPayment($member, $membership, $method);

        // System default for invoice: 14 days after the due date.
        $this->assertSame(
            Carbon::today()->addDays(14)->toDateString(),
            $payment->execution_date->toDateString()
        );
    }

    public function test_stored_offsets_are_clamped_into_the_supported_range(): void
    {
        $gym = Gym::factory()->create();
        $gym->setPaymentExecutionOffsets('cash', 999, -999);

        $offsets = $gym->fresh()->getPaymentExecutionOffsets('cash');

        $this->assertSame(PaymentMethod::MAX_EXECUTION_OFFSET, $offsets['initial']);
        $this->assertSame(PaymentMethod::MIN_EXECUTION_OFFSET, $offsets['recurring']);
    }

    public function test_unknown_payment_methods_fall_back_to_the_shared_default(): void
    {
        $gym = Gym::factory()->create();

        $offsets = $gym->getPaymentExecutionOffsets('mollie_creditcard');

        $this->assertSame(PaymentMethod::FALLBACK_EXECUTION_OFFSETS, $offsets);
    }
}
