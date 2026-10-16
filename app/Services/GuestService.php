<?php

namespace App\Services;

use App\Models\Gym;
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
            'status' => 'extern',
            'registration_source' => 'guest',
            'joined_date' => now(),
        ]);

        MemberAccessConfig::create([
            'member_id' => $member->id,
            'qr_code_enabled' => true,
        ]);

        return $member;
    }

    /**
     * Generate static QR code data for a guest member.
     *
     * IMPORTANT: This QR code uses a fundamentally different structure than the
     * standard member QR code (MemberController::generateQrCode) to prevent
     * misuse as a gym access pass. Differences:
     * - Uses 'type' => 'guest_service' (member QR has no type or 'rolling')
     * - HMAC message uses 'guest_service:' prefix
     * - Contains 'purpose' field instead of bare member_id/timestamp
     *
     * The scanner handles this format separately and checks the guest's
     * entitlements (day pass, visit card, solarium minutes) instead of a
     * membership.
     */
    public function generateStaticQrData(Member $member): array
    {
        $gym = $member->gym;
        $timestamp = Carbon::now()->format('Y-m-d\TH:i:s.uP');

        // Distinct HMAC prefix ensures this hash can never collide with access QR hashes
        $message = 'guest_service:'.$member->id.':'.$timestamp;
        $hash = hash_hmac('sha256', $message, $gym->getCurrentScannerKey());

        return [
            'qr_code' => json_encode([
                'type' => 'guest_service',
                'purpose' => 'service_credit',
                'mid' => (string) $member->id,
                'ts' => $timestamp,
                'hash' => $hash,
            ]),
            'member' => $member->only(['first_name', 'last_name', 'member_number']),
        ];
    }
}
