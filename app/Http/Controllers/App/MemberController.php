<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MemberController extends Controller
{
    public function profile(): JsonResponse
    {
        $member = request()->user();

        return response()->json([
            'success' => true,
            'data' => $member->only([
                'id', 'first_name', 'last_name', 'email',
                'phone', 'address', 'city', 'postal_code', 'member_number'
            ])
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
        ]);

        $member = request()->user();
        $member->update($request->only([
            'first_name', 'last_name', 'phone',
            'address', 'city', 'postal_code'
        ]));

        return response()->json([
            'success' => true,
            'data' => $member,
            'message' => 'Profil erfolgreich aktualisiert'
        ]);
    }

    public function contract(): JsonResponse
    {
        $member = request()->user();
        $contract = $member->activeMembership();

        return response()->json([
            'success' => true,
            'data' => $contract
        ]);
    }

    public function updateContract(Request $request): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|in:sepa,credit_card,bank_transfer',
            'iban' => 'required_if:payment_method,sepa|nullable|string',
            'account_holder' => 'required_if:payment_method,sepa|nullable|string',
        ]);

        $member = request()->user();
        $contract = $member->contract;

        if ($contract) {
            $contract->update($request->only([
                'payment_method', 'iban', 'account_holder'
            ]));
        }

        return response()->json([
            'success' => true,
            'data' => $contract,
            'message' => 'Zahlungsdaten erfolgreich aktualisiert'
        ]);
    }

    public function generateQrCode(): JsonResponse
    {
        /** @var Member $member */
        $member = request()->user();

        /** @var Gym $gym */
        $gym = $member->gym;

        // Zeitstempel im ISO 8601 Format mit Z-Suffix
        $timestamp = Carbon::now()->format('Y-m-d\TH:i:s.uP');

        // QR-Code-Daten
        $qrData = [
            'member_id' => (string) $member->id,
            'timestamp' => $timestamp
        ];

        // Hash generieren (über member_id und timestamp)
        $message = $member->id . ':' . $timestamp;
        $hashValue = hash_hmac(
            'sha256',
            $message,
            $gym->getCurrentScannerKey()
        );
        $qrData['hash'] = $hashValue;

        return response()->json([
            'success' => true,
            'data' => [
                'qr_code' => json_encode($qrData),
                'member' => $member->only(['first_name', 'last_name', 'member_number'])
            ]
        ]);
    }
}
