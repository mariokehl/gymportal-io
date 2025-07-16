<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateUsersCurrentGymSeeder extends Seeder
{
    public function run(): void
    {
        // FitZone users
        DB::table('users')
            ->whereIn('email', ['max@fitzone.de', 'lisa@fitzone.de', 'thomas@fitzone.de'])
            ->update(['current_gym_id' => 1]);

        // PowerFit user
        DB::table('users')
            ->where('email', 'anna@powerfit.de')
            ->update(['current_gym_id' => 2]);
    }
}
