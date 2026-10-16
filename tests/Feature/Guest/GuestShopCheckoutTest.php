<?php

namespace Tests\Feature\Guest;

use App\Models\GuestProduct;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\Payment;
use App\Services\CreditLedgerService;
use App\Services\MollieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Buying guest products in the guest app: the credit topped up by the gym is
 * applied first, the rest goes through a Mollie checkout.
 */
class GuestShopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $guest;

    private GuestProduct $minutes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create([
            'guest_enabled' => true,
            'mollie_config' => ['api_key' => 'test_'.str_repeat('a', 30), 'enabled_methods' => ['ideal']],
        ]);

        $this->guest = Member::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => 'extern',
            'age_verified' => true,
        ]);
        MemberAccessConfig::create(['member_id' => $this->guest->id, 'qr_code_enabled' => true]);

        $this->minutes = GuestProduct::create([
            'gym_id' => $this->gym->id,
            'type' => GuestProduct::TYPE_SOLARIUM_MINUTES,
            'name' => 'Solarium 20',
            'price' => 15.00,
            'quantity' => 20,
        ]);
    }

    private function guestToken(?Member $member = null): string
    {
        return ($member ?? $this->guest)->createToken('guest-pwa-full', ['guest-pwa', 'full'])->plainTextToken;
    }

    private function checkout(array $payload, ?string $token = null)
    {
        return $this->withHeader('Authorization', 'Bearer '.($token ?? $this->guestToken()))
            ->postJson('/api/guests/shop/checkout', $payload);
    }

    private function topUp(int $cents): void
    {
        app(CreditLedgerService::class)->credit($this->guest, $cents, description: 'Aufladung');
    }

    private function balance(): int
    {
        return app(CreditLedgerService::class)->getBalance($this->guest->fresh());
    }

    #[Test]
    public function credit_covering_the_price_pays_and_grants_at_once(): void
    {
        $this->topUp(2000);
        $this->mock(MollieService::class)->shouldNotReceive('createOneoffPaymentFor');

        $this->checkout(['product_id' => $this->minutes->id])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.checkout_url', null);

        $this->assertSame(500, $this->balance());

        $config = $this->guest->accessConfig->fresh();
        $this->assertSame(20, $config->solarium_minutes);
        $this->assertTrue($config->solarium_enabled);
    }

    #[Test]
    public function the_rest_not_covered_by_credit_goes_through_mollie(): void
    {
        $this->topUp(500);

        $molliePayment = new MolliePayment(new MollieApiClient);
        $molliePayment->_links = (object) ['checkout' => (object) ['href' => 'https://mollie.test/checkout']];

        $mollie = Mockery::mock(MollieService::class);
        $mollie->shouldReceive('createOneoffPaymentFor')
            ->once()
            ->withArgs(fn (Payment $payment, string $method) => (float) $payment->amount === 10.0 && $method === 'mollie_ideal')
            ->andReturn($molliePayment);
        $this->app->instance(MollieService::class, $mollie);

        $this->checkout(['product_id' => $this->minutes->id, 'payment_method' => 'mollie_ideal'])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.checkout_url', 'https://mollie.test/checkout')
            ->assertJsonPath('data.credit_used', 5);

        $this->assertSame(0, $this->balance());
        // Nothing is granted before the payment is confirmed.
        $this->assertSame(0, (int) $this->guest->accessConfig->fresh()->solarium_minutes);
    }

    #[Test]
    public function insufficient_credit_without_payment_method_touches_nothing(): void
    {
        $this->topUp(500);

        $this->checkout(['product_id' => $this->minutes->id])
            ->assertStatus(422);

        $this->assertSame(500, $this->balance());
        $this->assertSame(0, Payment::count());
    }

    #[Test]
    public function a_failing_mollie_checkout_gives_the_credit_back(): void
    {
        $this->topUp(500);

        $mollie = Mockery::mock(MollieService::class);
        $mollie->shouldReceive('createOneoffPaymentFor')->andThrow(new \RuntimeException('Mollie down'));
        $this->app->instance(MollieService::class, $mollie);

        $this->checkout(['product_id' => $this->minutes->id, 'payment_method' => 'mollie_ideal'])
            ->assertStatus(422);

        $this->assertSame(500, $this->balance());
        $this->assertSame('failed', Payment::first()->status);
    }

    #[Test]
    public function products_of_another_gym_cannot_be_bought(): void
    {
        $this->topUp(5000);
        $foreign = GuestProduct::create([
            'gym_id' => Gym::factory()->create()->id,
            'type' => GuestProduct::TYPE_DAY_PASS,
            'name' => 'Tageskarte',
            'price' => 10.00,
            'quantity' => 1,
        ]);

        $this->checkout(['product_id' => $foreign->id])->assertNotFound();

        $this->assertSame(5000, $this->balance());
    }

    #[Test]
    public function the_shop_requires_a_verified_age(): void
    {
        $this->guest->update(['age_verified' => false]);

        $this->checkout(['product_id' => $this->minutes->id])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'AGE_VERIFICATION_REQUIRED');
    }

    #[Test]
    public function guests_cannot_verify_their_age_themselves(): void
    {
        $this->guest->update(['age_verified' => false]);

        $this->withHeader('Authorization', 'Bearer '.$this->guestToken())
            ->postJson('/api/guests/age-verification')
            ->assertOk()
            ->assertJsonPath('age_verified', false);

        $this->assertFalse($this->guest->fresh()->isAgeVerified());
    }

    #[Test]
    public function member_app_tokens_are_rejected_by_guest_routes(): void
    {
        $token = $this->guest->createToken('member-pwa-full', ['member-pwa', 'full'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/guests/entitlements')
            ->assertForbidden();
    }

    #[Test]
    public function guest_tokens_are_rejected_by_member_app_routes(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->guestToken())
            ->getJson('/api/pwa/member/profile')
            ->assertForbidden();
    }

    #[Test]
    public function entitlements_and_credit_are_reported_separately(): void
    {
        $this->topUp(1250);
        $this->guest->accessConfig->update([
            'visit_card_enabled' => true,
            'visit_card_entries' => 7,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->guestToken())
            ->getJson('/api/guests/entitlements')
            ->assertOk()
            ->assertJsonPath('data.visit_card_entries', 7)
            ->assertJsonPath('data.solarium_minutes', 0)
            ->assertJsonPath('credit_balance', 12.5);
    }
}
