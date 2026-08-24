<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The frozen discount has to reach the Payment rows themselves — both the
 * initial fee and the recurring ones — while the setup fee stays untouched.
 */
class MembershipDiscountPaymentAmountTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->payments = app(PaymentService::class);
    }

    private function discountedPlan(array $overrides = []): MembershipPlan
    {
        $plan = MembershipPlan::factory()->create(array_merge([
            'gym_id' => $this->gym->id,
            'price' => 49.95,
            'setup_fee' => 29.00,
            'billing_cycle' => 'monthly',
            'discounts_enabled' => true,
            'commitment_months' => 12,
        ], $overrides));

        $plan->discountPhases()->createMany([
            ['sort_order' => 0, 'duration_months' => 3, 'price' => 19.95],
            ['sort_order' => 1, 'duration_months' => 9, 'price' => 34.95],
        ]);

        return $plan->refresh();
    }

    private function membership(MembershipPlan $plan, Carbon $startDate): Membership
    {
        $member = Member::factory()->create(['gym_id' => $this->gym->id]);

        return Membership::create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => $startDate->toDateString(),
            'status' => 'active',
        ]);
    }

    #[Test]
    public function the_initial_payment_uses_the_discounted_price(): void
    {
        $start = Carbon::today()->addDay();
        $membership = $this->membership($this->discountedPlan(), $start);

        $payment = $this->payments->createPendingPayment($membership->member, $membership, null);

        $this->assertNotNull($payment);
        $this->assertSame('19.95', (string) $payment->amount);
    }

    #[Test]
    public function the_setup_fee_is_never_discounted(): void
    {
        $start = Carbon::today()->addDay();
        $membership = $this->membership($this->discountedPlan(), $start);

        $payment = $this->payments->createSetupFeePayment($membership->member, $membership, null);

        $this->assertNotNull($payment);
        $this->assertSame('29.00', (string) $payment->amount);
    }

    #[Test]
    public function the_recurring_payments_follow_the_phases_then_the_regular_price(): void
    {
        $start = Carbon::today()->startOfMonth()->addDay();
        $membership = $this->membership($this->discountedPlan(), $start);

        $payments = $this->payments->createRecurringPayments(
            $membership->member,
            $membership,
            14,
            $start->copy()
        );

        $amounts = array_map(fn ($payment): string => (string) $payment->amount, $payments);

        // 3 months of the first phase, 9 of the second, then the plan price.
        $this->assertSame(
            ['19.95', '19.95', '19.95', '34.95', '34.95', '34.95', '34.95', '34.95', '34.95', '34.95', '34.95', '34.95', '49.95', '49.95'],
            $amounts
        );
    }

    #[Test]
    public function a_plan_without_discounts_still_charges_the_regular_price(): void
    {
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'price' => 49.95,
            'billing_cycle' => 'monthly',
            'discounts_enabled' => false,
        ]);

        $start = Carbon::today()->addDay();
        $membership = $this->membership($plan, $start);

        $payment = $this->payments->createPendingPayment($membership->member, $membership, null);

        $this->assertNotNull($payment);
        $this->assertSame('49.95', (string) $payment->amount);
    }

    #[Test]
    public function a_discounted_payment_records_why_it_is_reduced(): void
    {
        $start = Carbon::today()->addDay();
        $membership = $this->membership($this->discountedPlan(), $start);

        $payment = $this->payments->createPendingPayment($membership->member, $membership, null);

        $discount = $payment->metadata['discount'];

        $this->assertSame(1, $discount['phase']);
        $this->assertSame(1, $discount['period_start_month']);
        $this->assertSame(3, $discount['period_end_month']);
        $this->assertSame('49.95', $discount['regular_price']);
        $this->assertSame('19.95', $discount['discounted_price']);
        $this->assertSame('30.00', $discount['savings']);
        $this->assertNotNull($discount['version_key']);
    }

    #[Test]
    public function the_setup_fee_carries_no_discount_note(): void
    {
        $start = Carbon::today()->addDay();
        $membership = $this->membership($this->discountedPlan(), $start);

        $payment = $this->payments->createSetupFeePayment($membership->member, $membership, null);

        $this->assertArrayNotHasKey('discount', $payment->metadata ?? []);
    }

    #[Test]
    public function an_undiscounted_period_carries_no_discount_note(): void
    {
        $start = Carbon::today()->startOfMonth()->addDay();
        $membership = $this->membership($this->discountedPlan(), $start);

        $payments = $this->payments->createRecurringPayments(
            $membership->member,
            $membership,
            13,
            $start->copy()
        );

        // Month 13 is past the ladder, so it is billed at the regular price.
        $this->assertSame('49.95', (string) $payments[12]->amount);
        $this->assertNull($payments[12]->metadata['discount']);
    }

    #[Test]
    public function the_payment_records_the_ladder_version_it_was_signed_under(): void
    {
        $start = Carbon::today()->addDay();
        $plan = $this->discountedPlan();
        $membership = $this->membership($plan, $start);

        $signedVersion = $membership->discountPhases()->first()->version_key;

        $payment = $this->payments->createPendingPayment($membership->member, $membership, null);

        $this->assertSame($signedVersion, $payment->metadata['discount']['version_key']);
    }

    #[Test]
    public function later_plan_edits_do_not_change_a_running_contract(): void
    {
        $start = Carbon::today()->startOfMonth()->addDay();
        $plan = $this->discountedPlan();
        $membership = $this->membership($plan, $start);

        // The operator raises the promotional price after signup.
        $plan->discountPhases()->delete();
        $plan->discountPhases()->create([
            'sort_order' => 0,
            'duration_months' => 12,
            'price' => 44.95,
        ]);

        $payments = $this->payments->createRecurringPayments(
            $membership->member,
            $membership,
            2,
            $start->copy()
        );

        $this->assertSame('19.95', (string) $payments[0]->amount);
        $this->assertSame('19.95', (string) $payments[1]->amount);
    }
}
