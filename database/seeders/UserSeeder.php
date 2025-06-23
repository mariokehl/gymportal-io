<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Admin user
        DB::table('users')->insert([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@gymportal.io',
            'password' => Hash::make('password'),
            'role_id' => 1, // Admin role
            'phone' => '+49123456789',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Gym Owner users
        DB::table('users')->insert([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@fitzone.de',
            'password' => Hash::make('password'),
            'role_id' => 2, // Owner role
            'current_gym_id' => 1,
            'phone' => '+49987654321',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'first_name' => 'Anna',
            'last_name' => 'Schmidt',
            'email' => 'anna@powerfit.de',
            'password' => Hash::make('password'),
            'role_id' => 2, // Owner role
            'current_gym_id' => 2,
            'phone' => '+49123123123',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Staff users
        DB::table('users')->insert([
            'first_name' => 'Lisa',
            'last_name' => 'Müller',
            'email' => 'lisa@fitzone.de',
            'password' => Hash::make('password'),
            'role_id' => 3, // Staff role
            'current_gym_id' => 1,
            'phone' => '+49456456456',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Trainer users
        DB::table('users')->insert([
            'first_name' => 'Thomas',
            'last_name' => 'Weber',
            'email' => 'thomas@fitzone.de',
            'password' => Hash::make('password'),
            'role_id' => 4, // Trainer role
            'current_gym_id' => 1,
            'phone' => '+49789789789',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
