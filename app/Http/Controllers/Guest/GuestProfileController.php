<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\GuestPurchase;
use App\Models\Member;
use App\Services\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestProfileController extends Controller
{
    public function __construct(
        private GuestService $guestService
    ) {}

    /**
     * Get guest profile with balance summary.
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
                'balance' => $this->guestService->getBalance($member),
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
     * Initiate age verification (mock implementation).
     */
    public function initiateAgeVerification(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        if ($member->isAgeVerified()) {
            return response()->json([
                'success' => true,
                'message' => 'Alter bereits verifiziert.',
                'age_verified' => true,
            ]);
        }

        // Mock: auto-verify immediately
        // TODO: Replace with real KYC provider integration (e.g. IDnow, Veriff)
        $member->verifyAge(null);

        return response()->json([
            'success' => true,
            'message' => 'Altersverifizierung erfolgreich.',
            'age_verified' => true,
        ]);
    }

    /**
     * Generate static QR code for guest access.
     */
    public function generateQrCode(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();
        $member->load('accessConfig');

        $qrEnabled = $member->accessConfig?->qr_code_enabled ?? false;

        if (!$qrEnabled) {
            return response()->json([
                'success' => false,
                'message' => 'QR-Code ist nicht aktiviert.',
            ], 403);
        }

        $data = $this->guestService->generateStaticQrData($member);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get purchase history.
     */
    public function purchases(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        $purchases = GuestPurchase::forMember($member->id)
            ->with('product:id,name,type,price,value')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $purchases,
        ]);
    }

    /**
     * Get current balance/credits.
     */
    public function balance(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->guestService->getBalance($member),
        ]);
    }
}
