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
use Tests\TestCase;

/**
 * Dunning fees must never be collected by the payment run.
 *
 * A member that reached a dunning level is in arrears by definition, so a
 * direct debit over the fee would most likely bounce and would ignore the
 * payment period the dunning mail granted. The fee stays an open claim.
 */
class DunningFeeCollectionTest extends TestCase
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
     * @return array{0: Member, 1: Gym}
     */
    private function memberWithActiveSepa(): array
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id, 'price' => 49.99]);

        PaymentMethod::create([
            'member_id' => $member->id,
            'type' => 'sepa_direct_debit',
            'status' => 'active',
            'requires_mandate' => true,
            'is_default' => true,
            'iban' => 'DE02120300000000202051',
            'account_holder' => 'Test Member',
            'sepa_mandate_status' => 'active',
            'sepa_mandate_reference' => 'MANDATE-1',
            'sepa_mandate_signed_at' => now()->subYear(),
            'sepa_mandate_acknowledged' => true,
        ]);

        Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
        ]);

        return [$member, $gym];
    }

    /**
     * A dunning fee exactly as DunningService books it.
     */
    private function dunningFee(Member $member, Gym $gym): Payment
    {
        return Payment::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'amount' => 5.00,
            'currency' => 'EUR',
            'description' => 'Mahngebühr Stufe 2',
            'status' => 'pending',
            'due_date' => Carbon::today(),
            'execution_date' => Carbon::today(),
            'payment_method' => 'dunning_fee',
            'metadata' => ['dunning_level' => 2],
        ]);
    }

    private function runPaymentProcessing(): void
    {
        $this->artisan('memberships:process-payments', ['--days' => 0])
            ->assertSuccessful();
    }

    public function test_a_dunning_fee_is_not_collected(): void
    {
        [$member, $gym] = $this->memberWithActiveSepa();
        $fee = $this->dunningFee($member, $gym);

        $this->runPaymentProcessing();

        $fee->refresh();
        $this->assertSame('pending', $fee->status);
        $this->assertNull($fee->notes);
    }

    public function test_the_fee_stays_an_open_claim_for_the_next_level(): void
    {
        [$member, $gym] = $this->memberWithActiveSepa();
        $fee = $this->dunningFee($member, $gym);

        $this->runPaymentProcessing();

        // Still overdue, so it is part of the amount the next notice reports
        // and of the claims handed to the collection partner.
        $this->assertTrue(
            $member->payments()->overdue()->whereKey($fee->id)->exists(),
        );
    }

    public function test_regular_payments_are_still_collected(): void
    {
        [$member, $gym] = $this->memberWithActiveSepa();

        $regular = Payment::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'amount' => 49.99,
            'currency' => 'EUR',
            'description' => 'Mitgliedsbeitrag August',
            'status' => 'pending',
            'due_date' => Carbon::today(),
            'payment_method' => 'sepa_direct_debit',
        ]);

        $this->runPaymentProcessing();

        $this->assertNotSame('pending', $regular->fresh()->status);
    }

    public function test_a_payment_without_a_method_is_still_collected(): void
    {
        [$member, $gym] = $this->memberWithActiveSepa();

        // `payment_method` is nullable, and in SQL NULL != 'dunning_fee' is not
        // true — such a payment must not fall out of the run silently.
        $withoutMethod = Payment::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'amount' => 49.99,
            'currency' => 'EUR',
            'description' => 'Mitgliedsbeitrag ohne Zahlungsart',
            'status' => 'pending',
            'due_date' => Carbon::today(),
            'payment_method' => null,
        ]);

        $this->runPaymentProcessing();

        $this->assertNotSame('pending', $withoutMethod->fresh()->status);
    }
}
