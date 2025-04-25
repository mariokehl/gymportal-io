<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MembershipSeeder extends Seeder
{
    public function run()
    {
        // Get all members
        $members = DB::table('members')->get();

        foreach ($members as $member) {
            $gym_id = $member->gym_id;
            $membershipPlanIds = DB::table('membership_plans')
                ->where('gym_id', $gym_id)
                ->pluck('id')
                ->toArray();

            $startDate = Carbon::parse($member->joined_date);
            $endDate = $startDate->copy()->addYear();
            $status = $member->status === 'active' ? 'active' :
                     ($member->status === 'paused' ? 'paused' :
                     ($member->status === 'overdue' ? 'active' : 'cancelled'));

            $membershipPlanId = $membershipPlanIds[array_rand($membershipPlanIds)];

            DB::table('memberships')->insert([
                'member_id' => $member->id,
                'membership_plan_id' => $membershipPlanId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'status' => $status,
                'pause_start_date' => $status === 'paused' ? Carbon::now()->subDays(rand(1, 30))->format('Y-m-d') : null,
                'pause_end_date' => $status === 'paused' ? Carbon::now()->addDays(rand(1, 30))->format('Y-m-d') : null,
                'created_at' => $startDate,
                'updated_at' => $startDate,
            ]);
        }
    }
}
