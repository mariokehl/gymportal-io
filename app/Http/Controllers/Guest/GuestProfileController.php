<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Payment;
use App\Services\CreditLedgerService;
use App\Services\GuestCheckoutService;
use App\Services\GuestEntitlementService;
use App\Services\GuestQrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestProfileController extends Controller
{
    public function __construct(
        private GuestQrCodeService $guestQrCodes,
        private GuestEntitlementService $entitlements,
        private CreditLedgerService $creditLedger,
    ) {}

    /**
     * Guest profile with entitlements and credit.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => [
                    'id' => $member->id,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'birth_date' => $member->birth_date?->format('Y-m-d'),
                    'age_verified' => $member->isAgeVerified(),
                    'member_number' => $member->member_number,
                ],
                'entitlements' => $this->entitlements->summary($member),
                'credit_balance' => $this->creditLedger->getBalance($member) / 100,
                'gym' => [
                    'id' => $member->gym->id,
                    'name' => $member->gym->getDisplayName(),
                    'slug' => $member->gym->slug,
                    'logo_url' => $member->gym->logo_url,
                ],
            ],
        ]);
    }

    /**
     * Age verification status. The age is confirmed by the gym's staff (ID
     * check at the counter, "Alter verifizieren" on the member page); guests
     * cannot verify themselves.
     */
    public function initiateAgeVerification(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        if ($member->isAgeVerified()) {
            return response()->json([
                'success' => true,
                'message' => 'Dein Alter ist bereits bestätigt.',
                'age_verified' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bitte zeig beim nächsten Besuch deinen Ausweis an der Theke, damit wir dein Alter bestätigen können.',
            'age_verified' => false,
        ]);
    }

    /**
     * Current rolling guest service QR code. The app reloads it after
     * refresh_in seconds.
     */
    public function generateQrCode(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        if (! $member->accessConfig?->qr_code_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'QR-Code ist nicht aktiviert.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                ...$this->guestQrCodes->rollingCode($member),
                'member' => $member->only(['first_name', 'last_name', 'member_number']),
            ],
        ]);
    }

    /**
     * Purchase history.
     */
    public function purchases(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $payments = Payment::where('member_id', $member->id)
            ->where('metadata->type', GuestCheckoutService::PAYMENT_TYPE)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (Payment $payment) => [
                'id' => $payment->id,
                'description' => $payment->description,
                'amount' => (float) data_get($payment->metadata, 'original_amount_cents', $payment->amount * 100) / 100,
                'credit_used' => (int) data_get($payment->metadata, 'credit_redeemed_cents', 0) / 100,
                'status' => $payment->status,
                'created_at' => $payment->created_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Remaining solarium minutes, visit card entries and day pass.
     */
    public function entitlements(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->entitlements->summary($member),
            'credit_balance' => $this->creditLedger->getBalance($member) / 100,
        ]);
    }
}
