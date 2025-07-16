<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GymUserSeeder extends Seeder
{
    public function run()
    {
        // Assign staff to FitZone
        DB::table('gym_users')->insert([
            'gym_id' => 1, // FitZone Berlin
            'user_id' => 4, // Lisa Müller
            'role' => 'staff',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign trainer to FitZone
        DB::table('gym_users')->insert([
            'gym_id' => 1, // FitZone Berlin
            'user_id' => 5, // Thomas Weber
            'role' => 'trainer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
