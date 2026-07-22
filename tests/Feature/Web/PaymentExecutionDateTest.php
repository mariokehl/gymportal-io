<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentExecutionDateTest extends TestCase
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

    private function createPayment(Gym $gym, Member $member, array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'amount' => 25.00,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'status' => 'pending',
            'due_date' => now()->addDays(7)->toDateString(),
        ], $attributes));
    }

    #[Test]
    public function the_execution_date_can_be_set_on_a_pending_payment(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();
        $payment = $this->createPayment($gym, $member);

        $date = now()->addDays(5)->toDateString();

        $this->actingAs($owner)
            ->patchJson(route('payments.update-execution-date', $payment), ['execution_date' => $date])
            ->assertOk()
            ->assertJson(['execution_date' => $date]);

        $this->assertSame($date, $payment->fresh()->execution_date->toDateString());
    }

    #[Test]
    public function an_existing_execution_date_can_be_overwritten(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();
        $payment = $this->createPayment($gym, $member, [
            'execution_date' => now()->addDay()->toDateString(),
        ]);

        $date = now()->addDays(10)->toDateString();

        $this->actingAs($owner)
            ->patchJson(route('payments.update-execution-date', $payment), ['execution_date' => $date])
            ->assertOk();

        $this->assertSame($date, $payment->fresh()->execution_date->toDateString());
    }

    #[Test]
    public function today_is_accepted_as_execution_date(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();
        $payment = $this->createPayment($gym, $member);

        $this->actingAs($owner)
            ->patchJson(route('payments.update-execution-date', $payment), [
                'execution_date' => now()->toDateString(),
            ])
            ->assertOk();
    }

    #[Test]
    public function an_execution_date_in_the_past_is_rejected(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();
        $payment = $this->createPayment($gym, $member);

        $this->actingAs($owner)
            ->patchJson(route('payments.update-execution-date', $payment), [
                'execution_date' => now()->subDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('execution_date');

        $this->assertNull($payment->fresh()->execution_date);
    }

    #[Test]
    public function the_execution_date_cannot_be_changed_for_a_non_pending_payment(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();
        $payment = $this->createPayment($gym, $member, ['status' => 'paid']);

        $this->actingAs($owner)
            ->patchJson(route('payments.update-execution-date', $payment), [
                'execution_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertNull($payment->fresh()->execution_date);
    }

    #[Test]
    public function the_execution_date_cannot_be_changed_for_a_credit_topup(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();
        $payment = $this->createPayment($gym, $member, ['is_credit_topup' => true]);

        $this->actingAs($owner)
            ->patchJson(route('payments.update-execution-date', $payment), [
                'execution_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertNull($payment->fresh()->execution_date);
    }

    #[Test]
    public function the_execution_date_cannot_be_changed_once_a_mollie_payment_exists(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();
        $payment = $this->createPayment($gym, $member, ['mollie_payment_id' => 'tr_abc123']);

        $this->actingAs($owner)
            ->patchJson(route('payments.update-execution-date', $payment), [
                'execution_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertNull($payment->fresh()->execution_date);
    }

    #[Test]
    public function the_execution_date_cannot_be_changed_once_a_transaction_exists(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();
        $payment = $this->createPayment($gym, $member, ['transaction_id' => 'TX-1']);

        $this->actingAs($owner)
            ->patchJson(route('payments.update-execution-date', $payment), [
                'execution_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertNull($payment->fresh()->execution_date);
    }

    #[Test]
    public function the_execution_date_cannot_be_changed_for_a_payment_of_another_gym(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();
        [, $otherGym, $otherMember] = $this->ownerGymMember();
        $payment = $this->createPayment($otherGym, $otherMember);

        $this->actingAs($owner)
            ->patchJson(route('payments.update-execution-date', $payment), [
                'execution_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertForbidden();
    }
}
