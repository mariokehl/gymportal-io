<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\GuestProduct;
use App\Models\GuestPurchase;
use App\Models\Member;
use App\Models\Payment;
use App\Services\MollieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GuestShopController extends Controller
{
    public function __construct(
        private MollieService $mollieService
    ) {}

    /**
     * List available products for the guest's gym.
     */
    public function products(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $products = GuestProduct::where('gym_id', $member->gym_id)
            ->active()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('type');

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Create a Mollie payment for a guest product purchase.
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer',
            'payment_method' => 'required|string',
        ]);

        /** @var Member $member */
        $member = $request->user();
        $gym = $member->gym;

        if (!$gym->hasMollieConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Zahlungen sind für dieses Studio nicht konfiguriert.',
                'error_code' => 'PAYMENT_NOT_CONFIGURED',
            ], 422);
        }

        $product = GuestProduct::where('id', $request->product_id)
            ->where('gym_id', $gym->id)
            ->active()
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produkt nicht verfügbar.',
                'error_code' => 'PRODUCT_NOT_FOUND',
            ], 404);
        }

        try {
            return DB::transaction(function () use ($member, $gym, $product, $request) {
                // Create purchase record
                $purchase = GuestPurchase::create([
                    'member_id' => $member->id,
                    'guest_product_id' => $product->id,
                    'status' => 'pending',
                ]);

                // Create Mollie payment (also stores local Payment record via storePayment)
                $molliePayment = $this->mollieService->createPayment($gym, [
                    'amount' => $product->price,
                    'currency' => 'EUR',
                    'description' => $gym->getDisplayName() . ' - ' . $product->name,
                    'method' => $request->payment_method,
                    'redirectUrl' => config('app.guests_url', 'https://guests.gymportal.io')
                        . '/' . $gym->slug . '/payment-result?purchase_id=' . $purchase->id,
                    'webhookUrl' => url('/api/v1/public/mollie/webhook'),
                    'metadata' => [
                        'member_id' => $member->id,
                        'guest_purchase_id' => $purchase->id,
                        'type' => 'guest_purchase',
                    ],
                ]);

                // Find the payment record created by storePayment
                $payment = Payment::where('mollie_payment_id', $molliePayment->id)->first();
                $purchase->update(['payment_id' => $payment->id]);

                Log::info('Guest checkout initiated', [
                    'member_id' => $member->id,
                    'product_id' => $product->id,
                    'payment_id' => $payment->id,
                    'mollie_id' => $molliePayment->id,
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'checkout_url' => $molliePayment->getCheckoutUrl(),
                        'payment_id' => $payment->id,
                        'purchase_id' => $purchase->id,
                    ],
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Guest checkout failed', [
                'member_id' => $member->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler bei der Zahlungsverarbeitung. Bitte versuche es erneut.',
                'error_code' => 'CHECKOUT_FAILED',
            ], 500);
        }
    }

    /**
     * Check payment status (polling endpoint).
     */
    public function paymentStatus(Request $request, int $paymentId): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $payment = Payment::where('id', $paymentId)
            ->where('member_id', $member->id)
            ->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Zahlung nicht gefunden.',
            ], 404);
        }

        $purchaseId = data_get($payment->metadata, 'guest_purchase_id');
        $purchase = $purchaseId ? GuestPurchase::find($purchaseId) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'payment_status' => $payment->status,
                'purchase_status' => $purchase?->status,
                'purchase_active' => $purchase?->isActive() ?? false,
            ],
        ]);
    }
}
