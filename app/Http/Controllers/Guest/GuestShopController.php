<?php

namespace App\Http\Controllers\Guest;

use App\Exceptions\GuestActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guest\GuestCheckoutRequest;
use App\Models\GuestProduct;
use App\Models\Member;
use App\Models\Payment;
use App\Services\CreditLedgerService;
use App\Services\GuestCheckoutService;
use App\Services\MollieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class GuestShopController extends Controller
{
    public function __construct(
        private MollieService $mollieService,
        private GuestCheckoutService $checkoutService,
        private CreditLedgerService $creditLedger,
    ) {}

    /**
     * One-off payment methods of the guest's gym, for the part of a purchase
     * not covered by credit.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();
        $gym = $member->gym;

        if (! $gym->hasMollieConfigured()) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        try {
            $methods = $this->mollieService->getAvailableMethods($gym, 'oneoff');

            return response()->json([
                'success' => true,
                'data' => array_map(fn ($method) => [
                    'id' => 'mollie_'.$method->id,
                    'description' => $method->description,
                    'image' => $method->image->svg ?? null,
                ], array_values($methods)),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load payment methods', [
                'gym_id' => $gym->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Zahlungsmethoden konnten nicht geladen werden.',
            ], 500);
        }
    }

    /**
     * Active products of the guest's gym the guest may buy, grouped by type,
     * plus the credit available to pay for them.
     */
    public function products(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $products = GuestProduct::where('gym_id', $member->gym_id)
            ->active()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get(['id', 'type', 'name', 'description', 'price', 'quantity'])
            ->filter(fn (GuestProduct $product) => $product->isAvailableTo($member))
            ->groupBy('type');

        return response()->json([
            'success' => true,
            'data' => $products,
            'credit_balance' => $this->creditLedger->getBalance($member) / 100,
        ]);
    }

    /**
     * Buy a product: credit first, the rest over a Mollie checkout.
     */
    public function checkout(GuestCheckoutRequest $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $product = GuestProduct::where('gym_id', $member->gym_id)
            ->active()
            ->find($request->validated('product_id'));

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Produkt nicht verfügbar.',
                'error_code' => 'PRODUCT_NOT_FOUND',
            ], 404);
        }

        try {
            $result = $this->checkoutService->checkout(
                $member,
                $product,
                $request->validated('payment_method'),
                rtrim(config('gymportal.guests_url'), '/').'/'.$member->gym->slug.'/payment-result'
            );
        } catch (GuestActionException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'CHECKOUT_FAILED',
            ], 422);
        } catch (Throwable $e) {
            // Internal details (ledger checks, database errors) stay in the log.
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Der Kauf konnte nicht abgeschlossen werden. Bitte versuche es später erneut.',
                'error_code' => 'CHECKOUT_FAILED',
            ], 500);
        }

        $payment = $result['payment'];

        return response()->json([
            'success' => true,
            'data' => [
                'payment_id' => $payment->id,
                'payment_status' => $payment->status,
                'checkout_url' => $result['checkout_url'],
                'credit_used' => (int) data_get($payment->metadata, 'credit_redeemed_cents', 0) / 100,
            ],
        ]);
    }

    /**
     * Payment status for polling after the Mollie redirect.
     */
    public function paymentStatus(Request $request, int $paymentId): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $payment = Payment::where('member_id', $member->id)->find($paymentId);

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Zahlung nicht gefunden.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment_status' => $payment->status,
            ],
        ]);
    }
}
