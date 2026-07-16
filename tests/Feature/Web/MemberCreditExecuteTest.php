<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\CreditLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberCreditExecuteTest extends TestCase
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
    public function executing_a_credit_payment_redeems_credit_and_collects_the_rest_over_the_default_method(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();

        // 2,00 € credit available.
        app(CreditLedgerService::class)->credit($member, 200, description: 'Aufladung');

        // Default payment method for the remaining amount.
        PaymentMethod::create([
            'member_id' => $member->id,
            'type' => 'banktransfer',
            'is_default' => true,
            'status' => 'active',
        ]);

        // Pending 5,00 € payment that should prefer credit.
        $payment = $member->payments()->create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'amount' => 5.00,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'due_date' => now(),
            'payment_method' => 'credit',
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->post(route('members.payments.execute', ['member' => $member->id, 'payment' => $payment->id]))
            ->assertRedirect();

        $payment->refresh();

        // 2 € redeemed, remaining 3 € collected via banktransfer (marked paid).
        $this->assertSame('paid', $payment->status);
        $this->assertSame('banktransfer', $payment->payment_method);
        $this->assertEquals(3.00, (float) $payment->amount);
        $this->assertTrue((bool) $payment->metadata['credit_redeemed']);
        $this->assertSame(200, $payment->metadata['credit_redeemed_cents']);
        $this->assertSame(0, app(CreditLedgerService::class)->getBalance($member));
    }

    #[Test]
    public function executing_a_credit_payment_without_a_default_method_fails_clearly(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();

        app(CreditLedgerService::class)->credit($member, 200, description: 'Aufladung');

        $payment = $member->payments()->create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'amount' => 5.00,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'due_date' => now(),
            'payment_method' => 'credit',
            'status' => 'pending',
        ]);

        $this->actingAs($owner)
            ->post(route('members.payments.execute', ['member' => $member->id, 'payment' => $payment->id]))
            ->assertSessionHas('error');

        // Nothing was collected and, thanks to the rollback, no credit was consumed.
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(200, app(CreditLedgerService::class)->getBalance($member));
    }
}
