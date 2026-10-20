<?php

namespace Tests\Unit\Services;

use App\Models\GuestProduct;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\Payment;
use App\Services\CreditLedgerService;
use App\Services\GuestCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Webhook side of a guest purchase whose rest was paid over Mollie.
 */
class GuestCheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private GuestCheckoutService $service;

    private CreditLedgerService $ledger;

    private Member $guest;

    private GuestProduct $visitCard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GuestCheckoutService::class);
        $this->ledger = app(CreditLedgerService::class);

        $gym = Gym::factory()->create();
        $this->guest = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'extern']);
        MemberAccessConfig::create(['member_id' => $this->guest->id]);

        $this->visitCard = GuestProduct::create([
            'gym_id' => $gym->id,
            'type' => GuestProduct::TYPE_VISIT_CARD,
            'name' => '10er-Karte',
            'price' => 80.00,
            'quantity' => 10,
        ]);
    }

    /**
     * A pending purchase of the visit card, 30 € of it paid with credit.
     */
    private function pendingPurchase(): Payment
    {
        $this->ledger->credit($this->guest, 3000, description: 'Aufladung');

        $payment = Payment::create([
            'gym_id' => $this->guest->gym_id,
            'member_id' => $this->guest->id,
            'amount' => 80.00,
            'description' => 'Gastkauf: 10er-Karte',
            'status' => 'pending',
            'payment_method' => 'mollie_ideal',
            'due_date' => now()->toDateString(),
            'metadata' => [
                'type' => GuestCheckoutService::PAYMENT_TYPE,
                'guest_product_id' => $this->visitCard->id,
            ],
        ]);

        $this->ledger->settlePayment($payment);

        return $payment->fresh();
    }

    private function mollie(array $attributes): MolliePayment
    {
        $payment = new MolliePayment(new MollieApiClient);

        foreach ($attributes as $key => $value) {
            $payment->{$key} = $value;
        }

        return $payment;
    }

    private function entries(): int
    {
        return (int) $this->guest->accessConfig()->value('visit_card_entries');
    }

    #[Test]
    public function a_paid_purchase_is_granted_exactly_once(): void
    {
        $payment = $this->pendingPurchase();
        $payment->update(['status' => 'paid']);
        $paid = $this->mollie(['status' => 'paid', 'paidAt' => now()->toIso8601String()]);

        $this->service->handleWebhook($payment, $paid);
        $this->service->handleWebhook($payment, $paid);

        $this->assertSame(10, $this->entries());
    }

    #[Test]
    public function a_canceled_checkout_returns_the_redeemed_credit_once(): void
    {
        $payment = $this->pendingPurchase();
        $this->assertSame(0, $this->ledger->getBalance($this->guest));

        $payment->update(['status' => 'canceled']);
        $canceled = $this->mollie(['status' => 'canceled']);

        $this->service->handleWebhook($payment, $canceled);
        $this->service->handleWebhook($payment, $canceled);

        $this->assertSame(3000, $this->ledger->getBalance($this->guest->fresh()));
        $this->assertSame(0, $this->entries());
    }

    #[Test]
    public function a_refund_takes_back_the_unused_entries(): void
    {
        $payment = $this->pendingPurchase();
        $payment->update(['status' => 'paid']);
        $this->service->handleWebhook($payment, $this->mollie(['status' => 'paid', 'paidAt' => now()->toIso8601String()]));

        // The guest used three entries before the refund.
        $this->guest->accessConfig()->update(['visit_card_entries' => 7]);

        $this->service->handleWebhook($payment, $this->mollie([
            'status' => 'paid',
            'paidAt' => now()->toIso8601String(),
            'amountRefunded' => (object) ['value' => '50.00', 'currency' => 'EUR'],
        ]));

        $this->assertSame(0, $this->entries());
        $this->assertFalse((bool) $this->guest->accessConfig()->value('visit_card_enabled'));
    }
}
