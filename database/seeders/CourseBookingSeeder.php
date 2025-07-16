<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CourseBookingSeeder extends Seeder
{
    public function run()
    {
        // Get course schedules
        $courseSchedules = DB::table('course_schedules')->get();

        foreach ($courseSchedules as $schedule) {
            // Skip future courses
            if (Carbon::parse($schedule->date . ' ' . $schedule->start_time) > Carbon::now()) {
                continue;
            }

            $course = DB::table('courses')->where('id', $schedule->course_id)->first();
            $gym_id = $course->gym_id;

            // Get members of this gym
            $members = DB::table('members')
                ->where('gym_id', $gym_id)
                ->where('status', 'active')
                ->get();

            // Add random bookings for this schedule
            $bookingCount = rand(0, $course->capacity);
            $shuffledMembers = $members->shuffle()->take($bookingCount);

            foreach ($shuffledMembers as $member) {
                $status = Carbon::parse($schedule->date . ' ' . $schedule->start_time) < Carbon::now() ?
                         (rand(1, 10) > 2 ? 'attended' : 'no_show') : 'booked';

                DB::table('course_bookings')->insert([
                    'course_schedule_id' => $schedule->id,
                    'member_id' => $member->id,
                    'status' => $status,
                    'created_at' => Carbon::parse($schedule->date . ' ' . $schedule->start_time)->subDays(rand(1, 7)),
                    'updated_at' => Carbon::parse($schedule->date . ' ' . $schedule->start_time),
                ]);
            }
        }
    }
}
