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
 * Lazily loading the IBAN Mollie holds for a linked direct debit: only the
 * masked number may ever reach the browser.
 */
class PaymentMethodMollieIbanTest extends TestCase
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
            'iban' => null,
            'mollie_customer_id' => 'cst_test',
            'mollie_mandate_id' => 'mdt_test',
        ], $overrides));
    }

    private function fetch(User $user, Member $member, PaymentMethod $paymentMethod)
    {
        return $this->actingAs($user)
            ->getJson(route('members.payment-methods.mollie-iban', [
                'member' => $member,
                'paymentMethod' => $paymentMethod,
            ]));
    }

    #[Test]
    public function it_returns_the_masked_iban_mollie_holds(): void
    {
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldReceive('getMaskedMandateIban')
                ->once()
                ->withArgs(fn ($gym, $customerId, $mandateId) => $customerId === 'cst_test' && $mandateId === 'mdt_test')
                ->andReturn('DE02 **** **** **** **20 51');
        });

        $this->fetch($owner, $member, $paymentMethod)
            ->assertOk()
            ->assertExactJson(['masked_iban' => 'DE02 **** **** **** **20 51']);
    }

    #[Test]
    public function it_reports_a_failing_mollie_request_as_bad_gateway(): void
    {
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldReceive('getMaskedMandateIban')
                ->once()
                ->andThrow(new \RuntimeException('Mollie is unreachable'));
        });

        $this->fetch($owner, $member, $paymentMethod)
            ->assertStatus(502)
            ->assertJsonMissingPath('masked_iban')
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function it_reports_a_mandate_without_account_number(): void
    {
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldReceive('getMaskedMandateIban')->once()->andReturnNull();
        });

        $this->fetch($owner, $member, $paymentMethod)->assertNotFound();
    }

    #[Test]
    public function it_refuses_a_payment_method_without_mollie_link(): void
    {
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member, ['mollie_mandate_id' => null]);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('getMaskedMandateIban');
        });

        $this->fetch($owner, $member, $paymentMethod)->assertStatus(422);
    }

    #[Test]
    public function it_refuses_a_payment_method_that_is_not_billed_through_mollie(): void
    {
        [$owner, $member] = $this->gymWithMember();
        $paymentMethod = $this->paymentMethod($member, ['type' => 'sepa_direct_debit']);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('getMaskedMandateIban');
        });

        $this->fetch($owner, $member, $paymentMethod)->assertStatus(422);
    }

    #[Test]
    public function it_blocks_a_payment_method_from_another_gym(): void
    {
        [$owner] = $this->gymWithMember();
        [, $foreignMember] = $this->gymWithMember();
        $foreignPaymentMethod = $this->paymentMethod($foreignMember);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('getMaskedMandateIban');
        });

        $this->fetch($owner, $foreignMember, $foreignPaymentMethod)->assertForbidden();
    }

    #[Test]
    public function it_blocks_a_payment_method_of_another_member(): void
    {
        [$owner, $member] = $this->gymWithMember();
        [, $foreignMember] = $this->gymWithMember();
        $foreignPaymentMethod = $this->paymentMethod($foreignMember);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('getMaskedMandateIban');
        });

        $this->fetch($owner, $member, $foreignPaymentMethod)->assertNotFound();
    }

    #[Test]
    public function it_masks_an_iban_in_blocks_of_four_keeping_check_digits_and_last_four(): void
    {
        $this->assertSame('DE89 **** **** **** **30 00', PaymentMethod::maskIban('DE89370400440532013000'));
        $this->assertSame('DE89 **** **** **** **30 00', PaymentMethod::maskIban('de89 3704 0044 0532 0130 00'));
        $this->assertNull(PaymentMethod::maskIban(null));
        $this->assertNull(PaymentMethod::maskIban(''));
        $this->assertSame('****', PaymentMethod::maskIban('1234'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
