<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\GuestProduct;
use App\Models\GuestPurchase;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use Carbon\Carbon;

class GuestService
{
    /**
     * Create a new guest member with access config.
     */
    public function createGuestMember(Gym $gym, array $data): Member
    {
        $member = Member::create([
            'gym_id' => $gym->id,
            'member_number' => MemberService::generateMemberNumber($gym, 'G'),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'birth_date' => $data['birth_date'],
            'status' => 'active',
            'registration_source' => 'guest',
            'guest_access' => true,
            'guest_access_granted_at' => now(),
            'joined_date' => now(),
        ]);

        MemberAccessConfig::create([
            'member_id' => $member->id,
            'qr_code_enabled' => true,
        ]);

        return $member;
    }

    /**
     * Activate a guest purchase after successful payment.
     */
    public function activatePurchase(GuestPurchase $purchase): void
    {
        $purchase->activate();
    }

    /**
     * Get aggregated balance for a guest member.
     */
    public function getBalance(Member $member): array
    {
        $activePurchases = GuestPurchase::forMember($member->id)
            ->active()
            ->with('product')
            ->get();

        $balance = [
            'solarium_minutes' => 0,
            'visit_entries' => 0,
            'day_passes' => [],
        ];

        foreach ($activePurchases as $purchase) {
            switch ($purchase->product->type) {
                case 'solarium_minutes':
                    $balance['solarium_minutes'] += $purchase->credits_remaining;
                    break;
                case 'visit_card':
                    $balance['visit_entries'] += $purchase->credits_remaining;
                    break;
                case 'day_pass':
                    $balance['day_passes'][] = [
                        'id' => $purchase->id,
                        'valid_until' => $purchase->valid_until?->toIso8601String(),
                    ];
                    break;
            }
        }

        return $balance;
    }

    /**
     * Generate static QR code data for a guest member.
     */
    public function generateStaticQrData(Member $member): array
    {
        $gym = $member->gym;
        $timestamp = Carbon::now()->format('Y-m-d\TH:i:s.uP');

        $message = $member->id . ':' . $timestamp;
        $hash = hash_hmac('sha256', $message, $gym->getCurrentScannerKey());

        return [
            'qr_code' => json_encode([
                'member_id' => (string) $member->id,
                'timestamp' => $timestamp,
                'hash' => $hash,
            ]),
            'member' => $member->only(['first_name', 'last_name', 'member_number']),
        ];
    }
}
