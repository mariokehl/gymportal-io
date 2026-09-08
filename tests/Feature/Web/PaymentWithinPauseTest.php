<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Payments falling into a membership's pause period are highlighted in the
 * payments table. The backend calculates the flag via isDateWithinPause(), so
 * the frontend does not have to repeat the pause logic.
 */
class PaymentWithinPauseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makePayment(array $paymentAttributes, array $membershipAttributes = []): Payment
    {
        $gym = Gym::factory()->create();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $membership = Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'paused',
            'pause_start_date' => '2026-10-01',
            'pause_end_date' => '2026-10-31',
        ], $membershipAttributes));

        $payment = Payment::create(array_merge([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'amount' => 49.99,
            'currency' => 'EUR',
            'status' => 'pending',
            'payment_method' => 'sepa_direct_debit',
            'description' => 'Mitgliedsbeitrag',
        ], $paymentAttributes));

        return $payment->load('membership');
    }

    #[Test]
    public function a_payment_due_inside_the_pause_is_flagged(): void
    {
        $payment = $this->makePayment([
            'due_date' => '2026-10-15',
            'execution_date' => '2026-10-15',
        ]);

        $this->assertTrue($payment->is_within_pause);
    }

    #[Test]
    public function a_payment_outside_the_pause_is_not_flagged(): void
    {
        $payment = $this->makePayment([
            'due_date' => '2026-09-15',
            'execution_date' => '2026-09-15',
        ]);

        $this->assertFalse($payment->is_within_pause);
    }

    #[Test]
    public function the_pause_boundaries_are_inclusive(): void
    {
        $this->assertTrue($this->makePayment(['due_date' => '2026-10-01'])->is_within_pause);
        $this->assertTrue($this->makePayment(['due_date' => '2026-10-31'])->is_within_pause);
        $this->assertFalse($this->makePayment(['due_date' => '2026-09-30'])->is_within_pause);
        $this->assertFalse($this->makePayment(['due_date' => '2026-11-01'])->is_within_pause);
    }

    #[Test]
    public function the_execution_date_alone_is_enough_to_flag_a_payment(): void
    {
        $payment = $this->makePayment([
            'due_date' => '2026-09-28',
            'execution_date' => '2026-10-05',
        ]);

        $this->assertTrue($payment->is_within_pause);
    }

    #[Test]
    public function the_due_date_alone_is_enough_to_flag_a_payment(): void
    {
        $payment = $this->makePayment([
            'due_date' => '2026-10-05',
            'execution_date' => '2026-11-05',
        ]);

        $this->assertTrue($payment->is_within_pause);
    }

    #[Test]
    public function a_membership_without_a_pause_period_never_flags_a_payment(): void
    {
        $payment = $this->makePayment(
            ['due_date' => '2026-10-15'],
            ['status' => 'active', 'pause_start_date' => null, 'pause_end_date' => null],
        );

        $this->assertFalse($payment->is_within_pause);
    }

    #[Test]
    public function an_unloaded_membership_does_not_trigger_a_query_per_payment(): void
    {
        $payment = $this->makePayment(['due_date' => '2026-10-15']);

        $fresh = Payment::find($payment->id);

        // Without the eager loaded relation the flag stays false rather than
        // querying once per serialized row
        $this->assertFalse($fresh->relationLoaded('membership'));
        $this->assertFalse($fresh->is_within_pause);
    }

    #[Test]
    public function the_flag_is_serialized_for_the_frontend(): void
    {
        $payment = $this->makePayment(['due_date' => '2026-10-15']);

        $this->assertArrayHasKey('is_within_pause', $payment->toArray());
        $this->assertTrue($payment->toArray()['is_within_pause']);
    }
}
