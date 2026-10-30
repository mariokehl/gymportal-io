<?php

namespace App\Services;

use App\Exceptions\GuestActionException;
use App\Models\GuestProduct;
use App\Models\Member;
use App\Models\MemberCreditLedger;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Resources\Payment as MolliePayment;

/**
 * Purchase of guest products, credit first.
 *
 * The credit the gym topped up for the guest ("Guthaben") is applied first,
 * exactly like for membership fees. Whatever it does not cover is collected
 * through a one-off Mollie checkout. The product is granted once the payment
 * is fully paid; if the checkout fails, the redeemed credit goes back.
 */
class GuestCheckoutService
{
    public const PAYMENT_TYPE = 'guest_purchase';

    public function __construct(
        private CreditLedgerService $creditLedger,
        private MollieService $mollieService,
        private GuestEntitlementService $entitlements,
    ) {}

    public static function isGuestPurchase(Payment $payment): bool
    {
        return data_get($payment->metadata, 'type') === self::PAYMENT_TYPE;
    }

    /**
     * Start a purchase.
     *
     * @param  string|null  $paymentMethod  Mollie method for the part not covered by credit
     * @return array{payment: Payment, checkout_url: string|null}
     *
     * @throws GuestActionException with a guest-facing message
     */
    public function checkout(Member $member, GuestProduct $product, ?string $paymentMethod, string $redirectUrl): array
    {
        if (! $product->isAvailableTo($member)) {
            throw new GuestActionException($member->isAgeVerified()
                ? 'Solarium-Minuten sind erst ab '.GuestProduct::SOLARIUM_MIN_AGE.' Jahren erhältlich.'
                : 'Solarium-Minuten gibt es erst nach der Altersverifizierung vor Ort.');
        }

        $gym = $member->gym;
        $priceCents = $this->creditLedger->toCents((string) $product->price);
        $coveredByCredit = $this->creditLedger->getBalance($member) >= $priceCents;

        if (! $coveredByCredit) {
            if (! $gym->hasMollieConfigured()) {
                throw new GuestActionException('Dein Guthaben reicht nicht aus und Online-Zahlungen sind für dieses Studio nicht eingerichtet.');
            }

            if (! $paymentMethod) {
                throw new GuestActionException('Dein Guthaben reicht nicht aus. Bitte wähle eine Zahlungsart für den Restbetrag.');
            }
        }

        $payment = DB::transaction(function () use ($member, $product) {
            $payment = Payment::create([
                'gym_id' => $member->gym_id,
                'member_id' => $member->id,
                'amount' => $product->price,
                'currency' => 'EUR',
                'description' => 'Gastkauf: '.$product->service_description,
                'status' => 'pending',
                'payment_method' => 'credit',
                'due_date' => now()->toDateString(),
                'execution_date' => now()->toDateString(),
                'metadata' => [
                    'type' => self::PAYMENT_TYPE,
                    'guest_product_id' => $product->id,
                ],
            ]);

            $result = $this->creditLedger->settlePayment($payment);

            if ($result['fully_covered']) {
                $this->grantOnce($payment->fresh());
            }

            return $payment->fresh();
        });

        if ($payment->status === 'paid') {
            return ['payment' => $payment, 'checkout_url' => null];
        }

        try {
            $molliePayment = $this->mollieService->createOneoffPaymentFor($payment, $paymentMethod, $redirectUrl);
        } catch (\Throwable $e) {
            Log::error('Guest checkout: creating the Mollie payment failed', [
                'member_id' => $member->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $payment->update(['status' => 'failed', 'failed_at' => now()]);
            $this->restoreCreditOnce($payment);

            throw new GuestActionException('Fehler bei der Zahlungsverarbeitung. Bitte versuche es erneut.');
        }

        return ['payment' => $payment->fresh(), 'checkout_url' => $molliePayment->getCheckoutUrl()];
    }

    /**
     * React to a webhook update of a guest purchase. Every step is idempotent,
     * since Mollie may deliver the same state more than once.
     */
    public function handleWebhook(Payment $payment, MolliePayment $molliePayment): void
    {
        if ($molliePayment->isPaid()) {
            $this->grantOnce($payment);
        }

        if ($molliePayment->isFailed() || $molliePayment->isCanceled() || $molliePayment->isExpired()) {
            $this->restoreCreditOnce($payment);
        }

        $refunded = (float) ($molliePayment->amountRefunded->value ?? 0);
        $chargedBack = (float) ($molliePayment->amountChargedBack->value ?? 0);

        if ($refunded > 0 || $chargedBack > 0) {
            $this->revokeOnce($payment);
        }
    }

    private function grantOnce(Payment $payment): void
    {
        $this->onceFlag($payment, 'entitlement_granted', function (Payment $payment) {
            if ($payment->status !== 'paid') {
                return false;
            }

            $product = $this->productOf($payment);

            if (! $product) {
                Log::error('Guest purchase paid but product is missing', ['payment_id' => $payment->id]);

                return false;
            }

            $this->entitlements->grant($payment->member, $product, $payment);

            return true;
        });
    }

    /**
     * Give back the credit redeemed for a purchase whose checkout failed.
     */
    private function restoreCreditOnce(Payment $payment): void
    {
        $this->onceFlag($payment, 'credit_restored', function (Payment $payment) {
            $redeemedCents = (int) data_get($payment->metadata, 'credit_redeemed_cents', 0);

            if ($redeemedCents <= 0 || $payment->status === 'paid' || ! $payment->member) {
                return false;
            }

            $this->creditLedger->credit(
                member: $payment->member,
                amountCents: $redeemedCents,
                type: MemberCreditLedger::TYPE_REFUND,
                description: 'Guthaben-Rückbuchung: '.$payment->description,
                payment: $payment,
            );

            return true;
        });
    }

    private function revokeOnce(Payment $payment): void
    {
        $this->onceFlag($payment, 'entitlement_revoked', function (Payment $payment) {
            $product = $this->productOf($payment);

            if (! $product || empty($payment->metadata['entitlement_granted'])) {
                return false;
            }

            $this->entitlements->revoke($payment->member, $product, $payment);

            return true;
        });
    }

    /**
     * Run $action at most once per payment, guarded by a metadata flag under
     * a row lock. $action returns whether it did its job.
     */
    private function onceFlag(Payment $payment, string $flag, callable $action): void
    {
        DB::transaction(function () use ($payment, $flag, $action) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $locked || ! empty($locked->metadata[$flag])) {
                return;
            }

            if ($action($locked) !== true) {
                return;
            }

            $locked->update(['metadata' => [...($locked->metadata ?? []), $flag => now()->toIso8601String()]]);
        });
    }

    private function productOf(Payment $payment): ?GuestProduct
    {
        return GuestProduct::where('gym_id', $payment->gym_id)
            ->find(data_get($payment->metadata, 'guest_product_id'));
    }
}
