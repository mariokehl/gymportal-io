<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GymSeeder extends Seeder
{
    public function run()
    {
        // FitZone Gym
        DB::table('gyms')->insert([
            'name' => 'FitZone Berlin',
            'slug' => 'fitzone-berlin',
            'description' => 'Ein modernes Fitnessstudio im Herzen von Berlin',
            'address' => 'Berliner Straße 123',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'phone' => '+4930123456789',
            'email' => 'info@fitzone.de',
            'website' => 'https://fitzone.de',
            'owner_id' => 2, // Max Mustermann
            'subscription_plan' => 'premium',
            'subscription_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // PowerFit Gym
        DB::table('gyms')->insert([
            'name' => 'PowerFit München',
            'slug' => 'powerfit-muenchen',
            'description' => 'Das ultimative Krafttraining-Studio in München',
            'address' => 'Münchener Straße 456',
            'city' => 'München',
            'postal_code' => '80331',
            'country' => 'DE',
            'phone' => '+4989987654321',
            'email' => 'info@powerfit.de',
            'website' => 'https://powerfit.de',
            'owner_id' => 3, // Anna Schmidt
            'subscription_plan' => 'basic',
            'subscription_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
