<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;

class MemberService
{
    /**
     * Mitgliedsnummer generieren
     */
    public static function generateMemberNumber(Gym $gym, string $prefix = 'M'): string
    {
        $prefix = $prefix . str_pad($gym->id, 3, '0', STR_PAD_LEFT);
        $year = date('y');
        $lastNumber = Member::withTrashed()
            ->where('gym_id', $gym->id)
            ->where('member_number', 'like', $prefix . $year . '%')
            ->orderBy('member_number', 'desc')
            ->value('member_number');

        if ($lastNumber) {
            $lastSequence = intval(substr($lastNumber, -4));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix . $year . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }
}
