<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberCreditLedger;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\CreditLedgerService;
use App\Services\MollieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberCreditTopupTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym, 2: Member}
     */
    private function ownerGymMember(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        return [$owner->fresh(), $gym, $member];
    }

    #[Test]
    public function it_books_a_top_up_as_credit_and_payment_history_entry(): void
    {
        [$owner, , $member] = $this->ownerGymMember();

        $this->actingAs($owner)
            ->post(route('members.payments.store', $member->id), [
                'payment_type' => 'topup',
                'amount' => '300,00',
                'description' => 'Guthaben-Aufladung: Überweisung',
                'payment_method' => 'banktransfer',
                'status' => 'paid',
            ])
            ->assertRedirect();

        // Ledger got a top-up entry and the balance reflects it.
        $this->assertSame(1, MemberCreditLedger::where('member_id', $member->id)
            ->where('type', MemberCreditLedger::TYPE_TOPUP)->count());
        $this->assertSame(30000, app(CreditLedgerService::class)->getBalance($member->fresh()));

        // A paid payment record shows up in the history for auditability. It
        // keeps its real payment method and is flagged as a credit top-up.
        $payment = Payment::where('member_id', $member->id)->firstOrFail();
        $this->assertTrue($payment->is_credit_topup);
        $this->assertSame('banktransfer', $payment->payment_method);
        $this->assertSame('paid', $payment->status);
        $this->assertEquals(300.00, (float) $payment->amount);

        // The staff member who booked the top-up is recorded for the history.
        $this->assertSame($owner->fullName(), $payment->metadata['created_by_name']);
    }

    #[Test]
    public function a_top_up_without_a_payment_method_falls_back_to_banktransfer(): void
    {
        [$owner, , $member] = $this->ownerGymMember();

        $this->actingAs($owner)
            ->post(route('members.payments.store', $member->id), [
                'payment_type' => 'topup',
                'amount' => '50,00',
                'description' => 'Guthaben-Aufladung',
                'status' => 'paid',
            ])
            ->assertRedirect();

        $payment = Payment::where('member_id', $member->id)->firstOrFail();
        $this->assertTrue($payment->is_credit_topup);
        $this->assertSame('banktransfer', $payment->payment_method);
    }

    #[Test]
    public function a_mollie_top_up_stays_pending_and_grants_no_credit_yet(): void
    {
        [$owner, , $member] = $this->ownerGymMember();

        // Mollie must not be called for real: stub configuration + payment link.
        $mollie = Mockery::mock(MollieService::class);
        $mollie->shouldReceive('isConfigured')->andReturnTrue();
        $mollie->shouldReceive('createPaymentLink')->once();
        $this->app->instance(MollieService::class, $mollie);

        $this->actingAs($owner)
            ->post(route('members.payments.store', $member->id), [
                'payment_type' => 'topup',
                'amount' => '300,00',
                'description' => 'Guthaben-Aufladung',
                'payment_method' => 'mollie_paymentlink',
                'status' => 'paid',
            ])
            ->assertRedirect();

        // Payment is pending; no ledger entry and no balance yet.
        $payment = Payment::where('member_id', $member->id)->firstOrFail();
        $this->assertTrue($payment->is_credit_topup);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(0, MemberCreditLedger::where('member_id', $member->id)->count());
        $this->assertSame(0, app(CreditLedgerService::class)->getBalance($member->fresh()));
    }

    #[Test]
    public function it_rejects_a_zero_top_up(): void
    {
        [$owner, , $member] = $this->ownerGymMember();

        $this->actingAs($owner)
            ->post(route('members.payments.store', $member->id), [
                'payment_type' => 'topup',
                'amount' => '0',
                'description' => 'Ungültig',
                'status' => 'paid',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, MemberCreditLedger::where('member_id', $member->id)->count());
    }

    #[Test]
    public function a_regular_payment_is_not_treated_as_a_top_up(): void
    {
        [$owner, , $member] = $this->ownerGymMember();

        $this->actingAs($owner)
            ->post(route('members.payments.store', $member->id), [
                'payment_type' => 'regular',
                'amount' => '49.90',
                'description' => 'Monatsbeitrag',
                'due_date' => now()->toDateString(),
                'status' => 'pending',
            ])
            ->assertRedirect();

        $this->assertSame(0, MemberCreditLedger::where('member_id', $member->id)->count());
        $this->assertSame('pending', Payment::where('member_id', $member->id)->firstOrFail()->status);
    }

    #[Test]
    public function a_user_from_another_gym_cannot_top_up_a_members_credit(): void
    {
        [, , $member] = $this->ownerGymMember();

        // Owner of a different gym.
        $otherOwner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $otherGym = Gym::factory()->create(['owner_id' => $otherOwner->id]);
        $otherOwner->update(['current_gym_id' => $otherGym->id]);

        $this->actingAs($otherOwner->fresh())
            ->post(route('members.payments.store', $member->id), [
                'payment_type' => 'topup',
                'amount' => '50,00',
                'description' => 'Fremd-Aufladung',
                'status' => 'paid',
            ])
            ->assertForbidden();

        $this->assertSame(0, MemberCreditLedger::where('member_id', $member->id)->count());
    }
}
