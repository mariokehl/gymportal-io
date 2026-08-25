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
 * Removing a payment method is limited to retired ones: an active method is
 * still being collected from, so dropping it would strip a running membership
 * of the account data it is billed against.
 */
class PaymentMethodDeletionTest extends TestCase
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
    private function scenario(string $status = 'expired'): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $paymentMethod = PaymentMethod::create([
            'member_id' => $member->id,
            'type' => 'sepa_direct_debit',
            'status' => $status,
            'requires_mandate' => true,
            'iban' => 'DE02120300000000202051',
        ]);

        return [$owner->fresh(), $member, $paymentMethod];
    }

    private function destroy(User $user, Member $member, PaymentMethod $paymentMethod)
    {
        return $this->actingAs($user)
            ->delete(route('members.payment-methods.destroy', [
                'member' => $member,
                'paymentMethod' => $paymentMethod,
            ]));
    }

    #[Test]
    public function it_deletes_an_expired_payment_method(): void
    {
        [$owner, $member, $paymentMethod] = $this->scenario();

        $this->destroy($owner, $member, $paymentMethod)->assertSessionHas('success');

        $this->assertSoftDeleted($paymentMethod);
    }

    #[Test]
    public function it_refuses_to_delete_an_active_payment_method(): void
    {
        [$owner, $member, $paymentMethod] = $this->scenario('active');

        $this->destroy($owner, $member, $paymentMethod)->assertForbidden();

        $this->assertNotSoftDeleted($paymentMethod);
    }

    #[Test]
    public function it_refuses_to_delete_a_failed_payment_method(): void
    {
        // 'pending' would fit here too, but the enum migration skips SQLite, so
        // the status is not available in the test database.
        [$owner, $member, $paymentMethod] = $this->scenario('failed');

        $this->destroy($owner, $member, $paymentMethod)->assertForbidden();

        $this->assertNotSoftDeleted($paymentMethod);
    }

    #[Test]
    public function it_blocks_a_payment_method_from_another_gym(): void
    {
        [$owner] = $this->scenario();
        [, $foreignMember, $foreignPaymentMethod] = $this->scenario();

        $this->destroy($owner, $foreignMember, $foreignPaymentMethod)->assertForbidden();

        $this->assertNotSoftDeleted($foreignPaymentMethod);
    }

    #[Test]
    public function it_hides_a_deleted_method_from_the_member_file(): void
    {
        [$owner, $member, $paymentMethod] = $this->scenario();

        $this->destroy($owner, $member, $paymentMethod);

        $this->assertFalse($member->paymentMethods()->whereKey($paymentMethod->id)->exists());
    }
}
