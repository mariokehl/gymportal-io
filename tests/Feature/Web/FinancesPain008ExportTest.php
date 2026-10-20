<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PAIN.008 (SEPA direct debit) export of selected payments.
 */
class FinancesPain008ExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym = Gym::factory()->create([
            'owner_id' => $this->owner->id,
            'account_holder' => 'FitZone GmbH',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'creditor_identifier' => 'DE98ZZZ09999999999',
        ]);
        $this->owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $this->owner->fresh();
    }

    private function sepaPayment(Membership $membership): Payment
    {
        PaymentMethod::create([
            'member_id' => $membership->member_id,
            'type' => 'sepa_direct_debit',
            'status' => 'active',
            'iban' => 'DE02120300000000202051',
            'account_holder' => 'Julian Pulat',
            'sepa_mandate_reference' => 'MANDATE-TEST',
        ]);

        return Payment::create([
            'gym_id' => $this->gym->id,
            'member_id' => $membership->member_id,
            'membership_id' => $membership->id,
            'amount' => 29.99,
            'currency' => 'EUR',
            'description' => '1. Mitgliedsbeitrag: Basic',
            'status' => 'pending',
            'payment_method' => 'sepa_direct_debit',
            'due_date' => now()->toDateString(),
        ]);
    }

    private function membership(): Membership
    {
        $member = Member::factory()->create(['gym_id' => $this->gym->id, 'status' => 'active']);

        return Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => MembershipPlan::factory()->create(['gym_id' => $this->gym->id])->id,
            'start_date' => '2026-10-01',
        ]);
    }

    #[Test]
    public function it_exports_a_due_payment_whose_membership_was_deleted(): void
    {
        $membership = $this->membership();
        $payment = $this->sepaPayment($membership);
        $membership->delete();

        $response = $this->actingAs($this->owner)
            ->post(route('finances.export'), [
                'payment_ids' => [$payment->id],
                'export_type' => 'pain008',
            ])
            ->assertOk();

        $xml = $response->getContent();

        $this->assertStringContainsString('PAYMENT-'.$payment->id, $xml);
        $this->assertStringContainsString('<IBAN>DE02120300000000202051</IBAN>', $xml);
        // Without a signed mandate date the (deleted) membership's start counts.
        $this->assertStringContainsString('<DtOfSgntr>2026-10-01</DtOfSgntr>', $xml);
    }
}
