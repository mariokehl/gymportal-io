<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CourseScheduleSeeder extends Seeder
{
    public function run()
    {
        // Get all courses
        $courses = DB::table('courses')->get();

        // Create schedules for the next 4 weeks
        $startDate = Carbon::now()->startOfWeek();

        foreach ($courses as $course) {
            // Generate 2-3 weekly slots for each course
            $weeklySlots = [];
            $daysOfWeek = range(1, 5); // Monday to Friday
            shuffle($daysOfWeek);

            for ($i = 0; $i < rand(2, 3); $i++) {
                if (count($daysOfWeek) > 0) {
                    $day = array_pop($daysOfWeek);
                    $hour = rand(9, 20);
                    $weeklySlots[] = [
                        'day' => $day,
                        'start_hour' => $hour,
                        'end_hour' => $hour + 1,
                    ];
                }
            }

            // Create schedule entries for each weekly slot for 4 weeks
            for ($week = 0; $week < 4; $week++) {
                foreach ($weeklySlots as $slot) {
                    $date = $startDate->copy()->addWeeks($week)->addDays($slot['day'] - 1);

                    DB::table('course_schedules')->insert([
                        'course_id' => $course->id,
                        'instructor_id' => $course->instructor_id,
                        'date' => $date->format('Y-m-d'),
                        'start_time' => sprintf('%02d:00:00', $slot['start_hour']),
                        'end_time' => sprintf('%02d:00:00', $slot['end_hour']),
                        'room' => 'Raum ' . rand(1, 5),
                        'is_cancelled' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
