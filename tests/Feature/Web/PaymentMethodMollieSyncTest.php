<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\MollieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pushing a corrected IBAN over to Mollie: Mollie keeps the account data, so a
 * mandate has to be re-issued before the new IBAN is ever collected from.
 */
class PaymentMethodMollieSyncTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Member}
     */
    private function gymWithMember(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $member = Member::factory()->create(['gym_id' => $gym->id]);

        return [$owner->fresh(), $member];
    }

    private function paymentMethod(Member $member, array $overrides = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'member_id' => $member->id,
            'type' => 'mollie_directdebit',
            'status' => 'active',
            'requires_mandate' => true,
            'sepa_mandate_status' => 'active',
            'iban' => 'DE02120300000000202051',
            'mollie_customer_id' => 'cst_test',
            'mollie_mandate_id' => 'mdt_test',
        ], $overrides));
    }

    private function sync(User $user, Member $member, PaymentMethod $paymentMethod)
    {
        return $this->actingAs($user)
            ->put(route('members.payment-methods.sync-mollie-mandate', [
                'member' => $member,
                'paymentMethod' => $paymentMethod,
            ]));
    }

    #[Test]
    public function it_hands_the_payment_method_to_mollie_for_a_new_mandate(): void
    {
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member);

        $this->mock(MollieService::class, function ($mock) use ($paymentMethod) {
            $mock->shouldReceive('handleMolliePaymentMethod')
                ->once()
                ->withArgs(fn ($m, $pm) => $pm->id === $paymentMethod->id)
                ->andReturnTrue();
        });

        $this->sync($owner, $member, $paymentMethod)
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    #[Test]
    public function it_refuses_a_payment_method_that_is_not_billed_through_mollie(): void
    {
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member, ['type' => 'sepa_direct_debit']);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('handleMolliePaymentMethod');
        });

        $this->sync($owner, $member, $paymentMethod)->assertSessionHas('error');
    }

    #[Test]
    public function it_refuses_a_mandate_that_is_not_active_yet(): void
    {
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member, ['sepa_mandate_status' => 'pending']);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('handleMolliePaymentMethod');
        });

        $this->sync($owner, $member, $paymentMethod)->assertSessionHas('error');
    }

    #[Test]
    public function it_refuses_when_no_new_iban_is_stored(): void
    {
        // Mollie clears the local iban once it holds the account data, so an
        // empty field means there is nothing to transfer.
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member, ['iban' => '']);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('handleMolliePaymentMethod');
        });

        $this->sync($owner, $member, $paymentMethod)->assertSessionHas('error');
    }

    #[Test]
    public function it_reports_a_failing_mollie_transfer_instead_of_erroring_out(): void
    {
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldReceive('handleMolliePaymentMethod')
                ->once()
                ->andThrow(new \RuntimeException('Mollie is unreachable'));
        });

        $this->sync($owner, $member, $paymentMethod)
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    #[Test]
    public function it_blocks_a_payment_method_from_another_gym(): void
    {
        [$owner] = $this->gymWithMember();
        [, $foreignMember] = $this->gymWithMember();
        $foreignPaymentMethod = $this->paymentMethod($foreignMember);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('handleMolliePaymentMethod');
        });

        $this->sync($owner, $foreignMember, $foreignPaymentMethod)->assertForbidden();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
