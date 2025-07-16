<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseSeeder extends Seeder
{
    public function run()
    {
        // Courses for FitZone
        $fitZoneCourses = [
            [
                'name' => 'Yoga',
                'description' => 'Entspannende Yoga-Klasse für alle Levels',
                'capacity' => 15,
                'duration_minutes' => 60,
                'color' => '#4CAF50',
            ],
            [
                'name' => 'Spinning',
                'description' => 'Hochintensives Indoor-Cycling',
                'capacity' => 20,
                'duration_minutes' => 45,
                'color' => '#F44336',
            ],
            [
                'name' => 'Pilates',
                'description' => 'Pilates-Übungen zur Stärkung der Körpermitte',
                'capacity' => 12,
                'duration_minutes' => 60,
                'color' => '#2196F3',
            ],
            [
                'name' => 'HIIT',
                'description' => 'High Intensity Interval Training',
                'capacity' => 18,
                'duration_minutes' => 30,
                'color' => '#FF9800',
            ],
        ];

        foreach ($fitZoneCourses as $course) {
            DB::table('courses')->insert([
                'gym_id' => 1,
                'name' => $course['name'],
                'description' => $course['description'],
                'capacity' => $course['capacity'],
                'duration_minutes' => $course['duration_minutes'],
                'requires_booking' => true,
                'instructor_id' => 5, // Thomas Weber
                'color' => $course['color'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Courses for PowerFit
        $powerFitCourses = [
            [
                'name' => 'Krafttraining',
                'description' => 'Grundlagen des Krafttrainings',
                'capacity' => 10,
                'duration_minutes' => 60,
                'color' => '#9C27B0',
            ],
            [
                'name' => 'CrossFit',
                'description' => 'Funktionelles Training mit hoher Intensität',
                'capacity' => 15,
                'duration_minutes' => 60,
                'color' => '#FF5722',
            ],
            [
                'name' => 'Boxen',
                'description' => 'Boxtraining für Anfänger und Fortgeschrittene',
                'capacity' => 12,
                'duration_minutes' => 60,
                'color' => '#607D8B',
            ],
        ];

        foreach ($powerFitCourses as $course) {
            DB::table('courses')->insert([
                'gym_id' => 2,
                'name' => $course['name'],
                'description' => $course['description'],
                'capacity' => $course['capacity'],
                'duration_minutes' => $course['duration_minutes'],
                'requires_booking' => true,
                'instructor_id' => null,
                'color' => $course['color'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
