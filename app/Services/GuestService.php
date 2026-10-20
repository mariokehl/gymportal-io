<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;

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
}
