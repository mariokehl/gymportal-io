<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\CreditLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Member payment endpoints resolve {member} and {payment} through global route
 * model binding, so every action must verify that both belong to the caller's
 * current gym. Otherwise an operator of one gym could download invoices of, or
 * trigger collections for, members of another gym.
 */
class MemberPaymentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    private function owner(): User
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return $owner->fresh();
    }

    /**
     * @return array{0: Member, 1: Payment}
     */
    private function pendingPayment(User $owner): array
    {
        $member = Member::factory()->create(['gym_id' => $owner->current_gym_id]);

        $payment = Payment::create([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'amount' => 49.90,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'due_date' => now(),
            'status' => 'pending',
        ]);

        return [$member, $payment];
    }

    #[Test]
    public function it_forbids_downloading_an_invoice_of_another_gym(): void
    {
        $victim = $this->owner();
        $attacker = $this->owner();
        [$member, $payment] = $this->pendingPayment($victim);

        $this->actingAs($attacker)
            ->get(route('members.payments.invoice', ['member' => $member, 'payment' => $payment]))
            ->assertForbidden();
    }

    #[Test]
    public function it_forbids_executing_a_payment_of_another_gym(): void
    {
        $victim = $this->owner();
        $attacker = $this->owner();
        [$member, $payment] = $this->pendingPayment($victim);
        app(CreditLedgerService::class)->credit($member, 10000, description: 'Aufladung');

        $this->actingAs($attacker)
            ->post(route('members.payments.execute', ['member' => $member, 'payment' => $payment]))
            ->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(10000, app(CreditLedgerService::class)->getBalance($member));
    }

    #[Test]
    public function it_executes_a_payment_of_the_own_gym(): void
    {
        $owner = $this->owner();
        [$member, $payment] = $this->pendingPayment($owner);
        app(CreditLedgerService::class)->credit($member, 10000, description: 'Aufladung');

        $this->actingAs($owner)
            ->post(route('members.payments.execute', ['member' => $member, 'payment' => $payment]))
            ->assertRedirect();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    #[Test]
    public function it_forbids_batch_executing_payments_of_another_gym(): void
    {
        $victim = $this->owner();
        $attacker = $this->owner();
        [$member, $payment] = $this->pendingPayment($victim);

        $this->actingAs($attacker)
            ->post(route('members.payments.execute-batch', ['member' => $member]), [
                'payment_ids' => [$payment->id],
            ])
            ->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);
    }
}
