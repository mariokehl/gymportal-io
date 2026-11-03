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
 * Covers which failed payments the retry run picks up again.
 *
 * Only payments the gym collects itself are retried, and each one at most
 * --max-retries times. Payment links and manual methods are settled by the
 * member and must never be charged over a stored payment method.
 */
class RetryFailedPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    private Membership $membership;

    private PaymentMethod $defaultMethod;

    /**
     * Payment method ids handed to Mollie, keyed by local payment id.
     */
    private array $charged = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 08:00:00');

        $this->gym = Gym::factory()->create();
        $this->member = Member::factory()->create(['gym_id' => $this->gym->id, 'status' => 'active']);
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

        $this->defaultMethod = PaymentMethod::create([
            'member_id' => $this->member->id,
            'mollie_customer_id' => 'cst_test',
            'mollie_mandate_id' => 'mdt_test',
            'type' => 'mollie_directdebit',
            'status' => 'active',
            'is_default' => true,
        ]);

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

    private function createFailedPayment(?string $paymentMethod, array $metadata = []): Payment
    {
        return Payment::create([
            'gym_id' => $this->gym->id,
            'member_id' => $this->member->id,
            'membership_id' => $this->membership->id,
            'amount' => 49.99,
            'currency' => 'EUR',
            'status' => 'failed',
            'due_date' => '2026-11-01',
            'payment_method' => $paymentMethod,
            'description' => 'Mitgliedsbeitrag',
            'metadata' => $metadata,
        ]);
    }

    private function runRetry(): void
    {
        $this->artisan('memberships:retry-failed-payments', ['--max-retries' => 3])
            ->assertSuccessful();
    }

    #[Test]
    public function it_retries_a_failed_payment_below_the_retry_limit(): void
    {
        $payment = $this->createFailedPayment('mollie_directdebit', ['retry_count' => 2]);

        $this->runRetry();

        $payment->refresh();

        $this->assertSame($this->defaultMethod->id, $this->charged[$payment->id]);
        $this->assertSame(3, $payment->metadata['retry_count']);
    }

    #[Test]
    public function it_retries_a_failed_payment_without_a_retry_count(): void
    {
        $payment = $this->createFailedPayment('mollie_directdebit');

        $this->runRetry();

        $this->assertArrayHasKey($payment->id, $this->charged);
    }

    #[Test]
    public function it_skips_a_payment_that_reached_the_retry_limit(): void
    {
        $payment = $this->createFailedPayment('mollie_directdebit', ['retry_count' => 3]);

        $this->runRetry();

        $payment->refresh();

        $this->assertArrayNotHasKey($payment->id, $this->charged);
        $this->assertSame('failed', $payment->status);
    }

    public static function memberSettledPaymentMethods(): array
    {
        $types = ['mollie_paymentlink', ...PaymentMethod::MANUAL_TYPES];

        return array_map(fn (string $type) => [$type], array_combine($types, $types));
    }

    #[Test]
    #[DataProvider('memberSettledPaymentMethods')]
    public function it_never_retries_a_payment_the_member_settles_on_their_own(string $type): void
    {
        $payment = $this->createFailedPayment($type);

        $this->runRetry();

        $payment->refresh();

        $this->assertArrayNotHasKey($payment->id, $this->charged);
        $this->assertSame('failed', $payment->status);
        $this->assertNull($payment->mollie_payment_id);
    }
}
