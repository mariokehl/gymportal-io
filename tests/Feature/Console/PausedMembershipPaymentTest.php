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
 * Covers payments that come due while the membership is paused.
 *
 * No service is provided during a pause, so such a charge must never be
 * collected. ProcessMembershipPayments closes it as canceled and records the
 * pause period in the notes instead.
 */
class PausedMembershipPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-10 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * A membership with an active SEPA payment method, paused unless overridden.
     *
     * @return array{0: Membership, 1: Member, 2: Gym}
     */
    private function pausedMembership(array $attributes = []): array
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
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
            'sepa_mandate_reference' => 'MANDATE-1',
            'sepa_mandate_signed_at' => now()->subYear(),
            'sepa_mandate_acknowledged' => true,
        ]);

        $membership = Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
            'status' => 'paused',
            'pause_start_date' => '2026-08-01',
            'pause_end_date' => '2026-08-31',
        ], $attributes));

        return [$membership, $member, $gym];
    }

    private function createDuePayment(Membership $membership, Member $member, Gym $gym, array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'amount' => 49.99,
            'currency' => 'EUR',
            'status' => 'pending',
            'due_date' => '2026-08-10',
            'payment_method' => 'sepa_direct_debit',
            'description' => 'Mitgliedsbeitrag August',
        ], $attributes));
    }

    private function runProcessing(): void
    {
        $this->artisan('memberships:process-payments', ['--days' => 0])
            ->assertSuccessful();
    }

    #[Test]
    public function it_cancels_a_payment_due_within_the_pause_period(): void
    {
        [$membership, $member, $gym] = $this->pausedMembership();
        $payment = $this->createDuePayment($membership, $member, $gym);

        $this->runProcessing();

        $payment->refresh();

        $this->assertSame('canceled', $payment->status);
        $this->assertStringContainsString(
            'Keine Ausführung aufgrund der Pausenzeit von 01.08.2026 bis 31.08.2026.',
            $payment->notes
        );
        $this->assertSame('membership_paused', $payment->metadata['canceled_reason']);
        $this->assertSame('2026-08-01', $payment->metadata['pause_start_date']);
        $this->assertSame('2026-08-31', $payment->metadata['pause_end_date']);
    }

    #[Test]
    public function it_keeps_the_existing_notes_when_cancelling(): void
    {
        [$membership, $member, $gym] = $this->pausedMembership();
        $payment = $this->createDuePayment($membership, $member, $gym, [
            'notes' => 'Bestehende Notiz',
        ]);

        $this->runProcessing();

        $payment->refresh();

        $this->assertStringStartsWith('Bestehende Notiz | ', $payment->notes);
        $this->assertStringContainsString('Pausenzeit von 01.08.2026 bis 31.08.2026', $payment->notes);
    }

    #[Test]
    public function it_cancels_payments_due_on_the_pause_boundaries(): void
    {
        // The pause covers the whole boundary days. The end boundary is only
        // picked up for processing once an execution date pulls it into the
        // window, which must not change the decision.
        $boundaries = [
            ['2026-08-01', null],
            ['2026-08-31', '2026-08-10'],
        ];

        foreach ($boundaries as [$dueDate, $executionDate]) {
            [$membership, $member, $gym] = $this->pausedMembership();

            $payment = $this->createDuePayment($membership, $member, $gym, [
                'due_date' => $dueDate,
                'execution_date' => $executionDate,
            ]);

            $this->runProcessing();

            $payment->refresh();

            $this->assertSame('canceled', $payment->status, "Boundary {$dueDate} must be canceled");
        }
    }

    #[Test]
    public function it_ignores_the_execution_date_and_judges_by_the_due_date(): void
    {
        [$membership, $member, $gym] = $this->pausedMembership();

        // Due before the pause, but only collected inside it. The charge still
        // belongs to the unpaused period, so it has to be processed.
        $payment = $this->createDuePayment($membership, $member, $gym, [
            'due_date' => '2026-07-25',
            'execution_date' => '2026-08-05',
        ]);

        $this->runProcessing();

        $payment->refresh();

        $this->assertNotSame('canceled', $payment->status);
        $this->assertStringNotContainsString('Pausenzeit', (string) $payment->notes);
    }

    #[Test]
    public function it_cancels_a_payment_due_inside_the_pause_but_executed_outside(): void
    {
        [$membership, $member, $gym] = $this->pausedMembership();

        $payment = $this->createDuePayment($membership, $member, $gym, [
            'due_date' => '2026-08-05',
            'execution_date' => '2026-07-30',
        ]);

        $this->runProcessing();

        $this->assertSame('canceled', $payment->refresh()->status);
    }

    #[Test]
    public function it_processes_a_payment_due_outside_the_pause_period(): void
    {
        [$membership, $member, $gym] = $this->pausedMembership([
            'pause_start_date' => '2026-09-01',
            'pause_end_date' => '2026-09-30',
        ]);
        $payment = $this->createDuePayment($membership, $member, $gym);

        $this->runProcessing();

        $payment->refresh();

        $this->assertNotSame('canceled', $payment->status);
        $this->assertStringNotContainsString('Pausenzeit', (string) $payment->notes);
    }

    #[Test]
    public function it_processes_a_payment_when_the_membership_has_no_pause_period(): void
    {
        [$membership, $member, $gym] = $this->pausedMembership([
            'status' => 'active',
            'pause_start_date' => null,
            'pause_end_date' => null,
        ]);
        $payment = $this->createDuePayment($membership, $member, $gym);

        $this->runProcessing();

        $this->assertNotSame('canceled', $payment->refresh()->status);
    }

    #[Test]
    public function it_leaves_the_payment_untouched_in_test_mode(): void
    {
        [$membership, $member, $gym] = $this->pausedMembership();
        $payment = $this->createDuePayment($membership, $member, $gym);

        $this->artisan('memberships:process-payments', ['--days' => 0, '--test' => true])
            ->assertSuccessful();

        $this->assertSame('pending', $payment->refresh()->status);
    }
}
