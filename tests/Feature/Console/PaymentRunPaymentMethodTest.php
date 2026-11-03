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
use Mockery;
use Mollie\Api\Resources\Payment as MolliePayment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers which payment method the payment run collects a due payment with.
 *
 * A payment is collected with the method it was booked with, not blindly with
 * the member's default. Manual methods such as cash are never collected, and
 * the default only steps in when the booked method cannot be resolved.
 */
class PaymentRunPaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    private Membership $membership;

    private PaymentMethod $defaultMethod;

    /**
     * Payment method types handed to Mollie, keyed by local payment id.
     */
    private array $charged = [];

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

        $this->defaultMethod = $this->createPaymentMethod('mollie_directdebit', ['is_default' => true]);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldReceive('createPaymentWithoutStoring')
                ->andReturnUsing(function (Member $member, Payment $payment, PaymentMethod $method) {
                    $this->charged[$payment->id] = $method->id;

                    $molliePayment = Mockery::mock(MolliePayment::class);
                    $molliePayment->id = 'tr_test_'.$payment->id;

                    return $molliePayment;
                });
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createPaymentMethod(string $type, array $attributes = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'member_id' => $this->member->id,
            'mollie_customer_id' => 'cst_test',
            'mollie_mandate_id' => 'mdt_test',
            'type' => $type,
            'status' => 'active',
            'is_default' => false,
        ], $attributes));
    }

    private function createDuePayment(?string $paymentMethod, array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'gym_id' => $this->gym->id,
            'member_id' => $this->member->id,
            'membership_id' => $this->membership->id,
            'amount' => 9.99,
            'currency' => 'EUR',
            'status' => 'pending',
            'due_date' => '2026-11-01',
            'payment_method' => $paymentMethod,
            'description' => 'Add-on: Getränkeflat',
            'metadata' => ['payment_type' => 'addon_recurring'],
        ], $attributes));
    }

    private function runPaymentRun(): void
    {
        $this->artisan('memberships:process-payments', ['--days' => 0])
            ->assertSuccessful();
    }

    public static function manualPaymentMethods(): array
    {
        return array_map(fn (string $type) => [$type], array_combine(
            PaymentMethod::MANUAL_TYPES,
            PaymentMethod::MANUAL_TYPES
        ));
    }

    #[Test]
    #[DataProvider('manualPaymentMethods')]
    public function it_never_collects_a_payment_booked_with_a_manual_method(string $type): void
    {
        // The member only has Mollie direct debit; the add-on is billed in cash.
        $payment = $this->createDuePayment($type);

        $this->runPaymentRun();

        $payment->refresh();

        $this->assertArrayNotHasKey($payment->id, $this->charged);
        $this->assertSame('pending', $payment->status);
        $this->assertSame($type, $payment->payment_method);
        $this->assertNull($payment->mollie_payment_id);
    }

    #[Test]
    public function it_collects_with_the_booked_method_when_it_differs_from_the_default(): void
    {
        $creditCard = $this->createPaymentMethod('mollie_creditcard');
        $payment = $this->createDuePayment('mollie_creditcard');

        $this->runPaymentRun();

        $this->assertSame($creditCard->id, $this->charged[$payment->id]);
    }

    #[Test]
    public function it_falls_back_to_the_default_when_the_booked_method_no_longer_exists(): void
    {
        $creditCard = $this->createPaymentMethod('mollie_creditcard');
        $creditCard->delete();
        $payment = $this->createDuePayment('mollie_creditcard');

        $this->runPaymentRun();

        $this->assertSame($this->defaultMethod->id, $this->charged[$payment->id]);
    }

    #[Test]
    public function it_falls_back_to_the_default_when_the_booked_method_is_ambiguous(): void
    {
        $this->createPaymentMethod('mollie_creditcard');
        $this->createPaymentMethod('mollie_creditcard');
        $payment = $this->createDuePayment('mollie_creditcard');

        $this->runPaymentRun();

        $this->assertSame($this->defaultMethod->id, $this->charged[$payment->id]);
    }

    #[Test]
    public function it_ignores_inactive_methods_of_the_booked_type(): void
    {
        // A replaced mandate stays behind as expired next to the active one.
        $this->createPaymentMethod('mollie_creditcard', ['status' => 'expired']);
        $creditCard = $this->createPaymentMethod('mollie_creditcard');
        $payment = $this->createDuePayment('mollie_creditcard');

        $this->runPaymentRun();

        $this->assertSame($creditCard->id, $this->charged[$payment->id]);
    }

    #[Test]
    public function it_falls_back_to_the_default_when_the_booked_method_is_no_longer_active(): void
    {
        $this->createPaymentMethod('mollie_creditcard', ['status' => 'expired']);
        $payment = $this->createDuePayment('mollie_creditcard');

        $this->runPaymentRun();

        $this->assertSame($this->defaultMethod->id, $this->charged[$payment->id]);
    }

    #[Test]
    public function it_collects_with_the_default_when_the_payment_names_no_method(): void
    {
        $payment = $this->createDuePayment(null);

        $this->runPaymentRun();

        $this->assertSame($this->defaultMethod->id, $this->charged[$payment->id]);
    }
}
