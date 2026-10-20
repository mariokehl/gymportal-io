<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\WalletPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class GuestWalletController extends Controller
{
    public function __construct(
        private WalletPassService $walletPassService
    ) {}

    /**
     * Generate and download Apple Wallet pass (.pkpass).
     */
    public function applePass(Request $request): Response
    {
        /** @var Member $member */
        $member = $request->user();
        $member->load(['gym', 'accessConfig']);

        if (! $member->accessConfig?->qr_code_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'QR-Code ist nicht aktiviert.',
            ], 403);
        }

        try {
            $passData = $this->walletPassService->generateApplePass($member);

            return response($passData, 200, [
                'Content-Type' => 'application/vnd.apple.pkpass',
                'Content-Disposition' => 'attachment; filename="gymportal-guest.pkpass"',
            ]);
        } catch (\Exception $e) {
            $this->logFailure('apple', $member, $e);

            return response()->json([
                'success' => false,
                'message' => 'Apple Wallet Pass konnte nicht erstellt werden.',
                'error_code' => 'WALLET_GENERATION_FAILED',
            ], 500);
        }
    }

    /**
     * Generate Google Wallet save URL.
     */
    public function googlePass(Request $request): JsonResponse
    {
        /** @var Member $member */
        $member = $request->user();
        $member->load(['gym', 'accessConfig']);

        if (! $member->accessConfig?->qr_code_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'QR-Code ist nicht aktiviert.',
            ], 403);
        }

        try {
            $saveUrl = $this->walletPassService->generateGoogleWalletUrl($member);

            return response()->json([
                'success' => true,
                'data' => [
                    'save_url' => $saveUrl,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logFailure('google', $member, $e);

            return response()->json([
                'success' => false,
                'message' => 'Google Wallet Pass konnte nicht erstellt werden.',
                'error_code' => 'WALLET_GENERATION_FAILED',
            ], 500);
        }
    }

    /**
     * A failing pass usually means a missing or expired certificate, which
     * affects every guest of the platform, so it has to show up in the log.
     */
    private function logFailure(string $wallet, Member $member, \Exception $e): void
    {
        Log::error('Wallet pass generation failed', [
            'wallet' => $wallet,
            'member_id' => $member->id,
            'gym_id' => $member->gym_id,
            'error' => $e->getMessage(),
        ]);
    }
}
