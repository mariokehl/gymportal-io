<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\GuestProduct;
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
     * List available oneoff payment methods for the guest's gym.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();
        $gym = $member->gym;

        if (!$gym->hasMollieConfigured()) {
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
                    'id' => 'mollie_' . $method->id,
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
            $molliePayment = $this->mollieService->createPayment($gym, [
                'amount' => $product->price,
                'currency' => 'EUR',
                'description' => 'Einmalkauf: ' . $product->service_description,
                'method' => $request->payment_method,
                'redirectUrl' => config('gymportal.guests_url')
                    . '/' . $gym->slug . '/payment-result',
                'webhookUrl' => url('/api/v1/public/mollie/webhook'),
                'metadata' => [
                    'description' => $product->service_description,
                    'member_id' => $member->id,
                    'guest_product_id' => $product->id,
                    'type' => 'guest_purchase',
                ],
            ]);

            $payment = Payment::where('mollie_payment_id', $molliePayment->id)->first();

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
                ],
            ]);
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

        return response()->json([
            'success' => true,
            'data' => [
                'payment_status' => $payment->status,
            ],
        ]);
    }
}
