<?php

namespace Tests\Feature\Console;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\MollieService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers that the payment run settles due zero-amount payments as paid,
 * regardless of the payment method they were booked with.
 */
class ZeroAmountPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    private Membership $membership;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 08:00:00');

        $this->gym = Gym::factory()->create();
        $this->member = Member::factory()->create(['gym_id' => $this->gym->id]);
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'price' => 49.99,
            'billing_cycle' => 'monthly',
            'is_free_trial_plan' => false,
        ]);
        $this->membership = Membership::factory()->create([
            'member_id' => $this->member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => 'active',
        ]);

        // A zero-amount payment must never reach Mollie.
        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('createPaymentWithoutStoring');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createDuePayment(?string $paymentMethod, float $amount = 0.0): Payment
    {
        return Payment::create([
            'gym_id' => $this->gym->id,
            'member_id' => $this->member->id,
            'membership_id' => $this->membership->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => 'pending',
            'due_date' => '2026-11-01',
            'payment_method' => $paymentMethod,
            'description' => 'Mitgliedsbeitrag',
            'metadata' => ['payment_type' => 'membership_fee'],
        ]);
    }

    private function runPaymentRun(array $options = []): void
    {
        $this->artisan('memberships:process-payments', array_merge(['--days' => 0], $options))
            ->assertSuccessful();
    }

    public static function paymentMethods(): array
    {
        $types = [
            null,
            'mollie_paymentlink',
            'mollie_directdebit',
            'mollie_creditcard',
            'sepa_direct_debit',
            ...PaymentMethod::MANUAL_TYPES,
        ];

        $cases = [];
        foreach ($types as $type) {
            $cases[$type ?? 'none'] = [$type];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('paymentMethods')]
    public function it_marks_a_zero_amount_payment_as_paid_for_every_method(?string $type): void
    {
        $payment = $this->createDuePayment($type);

        $this->runPaymentRun();

        $payment->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->paid_date);
        $this->assertSame($type, $payment->payment_method);
        $this->assertNull($payment->mollie_payment_id);
        $this->assertSame('zero_amount', $payment->metadata['paid_reason']);
    }

    #[Test]
    public function it_marks_a_zero_amount_payment_as_paid_without_any_payment_method_on_file(): void
    {
        // The member has no payment method at all.
        $payment = $this->createDuePayment(null);

        $this->runPaymentRun();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    #[Test]
    public function it_leaves_a_payment_with_an_amount_untouched_for_manual_methods(): void
    {
        $payment = $this->createDuePayment('cash', 9.99);

        $this->runPaymentRun();

        $this->assertSame('pending', $payment->fresh()->status);
    }

    #[Test]
    public function it_does_not_change_a_zero_amount_payment_in_test_mode(): void
    {
        $payment = $this->createDuePayment('cash');

        $this->runPaymentRun(['--test' => true]);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    #[Test]
    public function it_does_not_touch_a_zero_amount_payment_that_is_not_due_yet(): void
    {
        $payment = $this->createDuePayment('cash');
        $payment->update(['due_date' => '2026-12-01']);

        $this->runPaymentRun();

        $this->assertSame('pending', $payment->fresh()->status);
    }
}
