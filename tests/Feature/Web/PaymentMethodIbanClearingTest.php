<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Retiring a SEPA method: expiring it is the one case where the IBAN may be
 * dropped, so the stored account data does not outlive the method itself.
 */
class PaymentMethodIbanClearingTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Member, 2: PaymentMethod}
     */
    private function sepaScenario(string $type = 'sepa_direct_debit'): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $paymentMethod = PaymentMethod::create([
            'member_id' => $member->id,
            'type' => $type,
            'status' => 'active',
            'requires_mandate' => true,
            'sepa_mandate_status' => 'active',
            'iban' => 'DE02120300000000202051',
            'account_holder' => 'Erika Mustermann',
        ]);

        return [$owner->fresh(), $member, $paymentMethod];
    }

    private function update(User $user, Member $member, PaymentMethod $paymentMethod, array $payload)
    {
        return $this->actingAs($user)
            ->put(route('members.payment-methods.update', [
                'member' => $member,
                'paymentMethod' => $paymentMethod,
            ]), $payload);
    }

    #[Test]
    public function it_clears_the_iban_when_the_method_is_expired(): void
    {
        [$owner, $member, $paymentMethod] = $this->sepaScenario();

        $this->update($owner, $member, $paymentMethod, [
            'status' => 'expired',
            'is_default' => false,
            'iban' => '',
        ])->assertSessionHas('success');

        $paymentMethod->refresh();

        $this->assertSame('expired', $paymentMethod->status);
        $this->assertNull($paymentMethod->iban);
    }

    #[Test]
    public function it_clears_the_iban_of_an_expired_mollie_direct_debit(): void
    {
        [$owner, $member, $paymentMethod] = $this->sepaScenario('mollie_directdebit');

        $this->update($owner, $member, $paymentMethod, [
            'status' => 'expired',
            'is_default' => false,
            'iban' => '',
        ])->assertSessionHas('success');

        $this->assertNull($paymentMethod->refresh()->iban);
    }

    #[Test]
    public function it_keeps_a_submitted_iban_normalised(): void
    {
        [$owner, $member, $paymentMethod] = $this->sepaScenario();

        $this->update($owner, $member, $paymentMethod, [
            'status' => 'active',
            'is_default' => false,
            'iban' => 'de89 3704 0044 0532 0130 00',
        ])->assertSessionHas('success');

        $this->assertSame('DE89370400440532013000', $paymentMethod->refresh()->iban);
    }

    #[Test]
    public function it_blocks_a_payment_method_from_another_gym(): void
    {
        [$owner] = $this->sepaScenario();
        [, $foreignMember, $foreignPaymentMethod] = $this->sepaScenario();

        $this->update($owner, $foreignMember, $foreignPaymentMethod, [
            'status' => 'expired',
            'is_default' => false,
            'iban' => '',
        ])->assertForbidden();

        $this->assertNotNull($foreignPaymentMethod->refresh()->iban);
    }
}
