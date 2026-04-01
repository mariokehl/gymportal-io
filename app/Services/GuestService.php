<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\GuestProduct;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
     * Activate a guest purchase after successful payment by crediting MemberAccessConfig.
     */
    public function activatePurchase(Payment $payment, GuestProduct $product): void
    {
        $member = Member::findOrFail($payment->member_id);
        $config = $member->getOrCreateAccessConfig();

        switch ($product->type) {
            case 'solarium_minutes':
                $config->update(['solarium_enabled' => true]);
                $config->addCredit('solarium', $product->value);
                break;

            case 'visit_card':
                $config->update(['visit_card_enabled' => true]);
                $config->addCredit('visit_card', $product->value);
                break;

            case 'day_pass':
                $config->update([
                    'day_pass_enabled' => true,
                    'day_pass_valid_until' => now()->endOfDay(),
                ]);
                break;
        }

        Log::info('Guest purchase credited to MemberAccessConfig', [
            'member_id' => $member->id,
            'product_type' => $product->type,
            'product_value' => $product->value,
            'payment_id' => $payment->id,
        ]);
    }

    /**
     * Get aggregated balance for a guest member from MemberAccessConfig.
     */
    public function getBalance(Member $member): array
    {
        $config = $member->accessConfig;

        if (!$config) {
            return [
                'solarium_minutes' => 0,
                'visit_entries' => 0,
                'day_pass' => null,
            ];
        }

        return [
            'solarium_minutes' => $config->solarium_enabled ? $config->solarium_minutes : 0,
            'visit_entries' => $config->visit_card_enabled ? $config->visit_card_entries : 0,
            'day_pass' => $config->day_pass_enabled && $config->isDayPassValid()
                ? $config->day_pass_valid_until?->toIso8601String()
                : null,
        ];
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
