<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckInSeeder extends Seeder
{
    public function run()
    {
        // Get all active members
        $members = DB::table('members')
            ->where('status', 'active')
            ->get();

        foreach ($members as $member) {
            // Generate random check-ins for the past 30 days
            $checkInCount = rand(3, 15);
            $days = array_rand(range(1, 30), $checkInCount);

            foreach ((array)$days as $day) {
                $checkInTime = Carbon::now()->subDays($day)->setHour(rand(8, 20))->setMinute(rand(0, 59));
                $checkOutTime = (rand(1, 10) > 2) ? $checkInTime->copy()->addHours(rand(1, 3)) : null;

                DB::table('check_ins')->insert([
                    'member_id' => $member->id,
                    'gym_id' => $member->gym_id,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => $checkOutTime,
                    'check_in_method' => rand(0, 1) ? 'qr_code' : 'manual',
                    'checked_in_by' => rand(0, 1) ? null : ($member->gym_id == 1 ? 4 : null), // Lisa Müller for FitZone
                    'created_at' => $checkInTime,
                    'updated_at' => $checkOutTime ?? $checkInTime,
                ]);
            }
        }
    }
}
