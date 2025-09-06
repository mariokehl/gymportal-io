<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Carbon\Carbon;

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

    /**
     * Mitgliedschaft erstellen
     */
    public function createMembership(Member $member, MembershipPlan $plan, string $status = 'active'): Membership
    {
        return Membership::create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => $member->joined_date,
            'end_date' => Carbon::parse($member->joined_date)
                ->addMonths($plan->commitment_months)
                ->subDay(), // Einen Tag abziehen für korrektes Vertragsende
            'status' => $status
        ]);
    }
}
