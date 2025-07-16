<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MemberSeeder extends Seeder
{
    public function run()
    {
        // FitZone Members
        for ($i = 1; $i <= 25; $i++) {
            $joinedDate = Carbon::now()->subDays(rand(1, 365));

            DB::table('members')->insert([
                'gym_id' => 1,
                'member_number' => 'FZ' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'first_name' => $this->getRandomFirstName(),
                'last_name' => $this->getRandomLastName(),
                'email' => 'member' . $i . '@example.com',
                'phone' => '+49' . rand(1000000000, 9999999999),
                'birth_date' => Carbon::now()->subYears(rand(18, 70))->format('Y-m-d'),
                'address' => 'Musterstraße ' . rand(1, 100),
                'city' => 'Berlin',
                'postal_code' => '10' . rand(100, 999),
                'country' => 'DE',
                'status' => $this->getRandomStatus(),
                'joined_date' => $joinedDate->format('Y-m-d'),
                'created_at' => $joinedDate,
                'updated_at' => $joinedDate,
            ]);
        }

        // PowerFit Members
        for ($i = 1; $i <= 15; $i++) {
            $joinedDate = Carbon::now()->subDays(rand(1, 180));

            DB::table('members')->insert([
                'gym_id' => 2,
                'member_number' => 'PF' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'first_name' => $this->getRandomFirstName(),
                'last_name' => $this->getRandomLastName(),
                'email' => 'pfmember' . $i . '@example.com',
                'phone' => '+49' . rand(1000000000, 9999999999),
                'birth_date' => Carbon::now()->subYears(rand(18, 70))->format('Y-m-d'),
                'address' => 'Hauptstraße ' . rand(1, 100),
                'city' => 'München',
                'postal_code' => '80' . rand(100, 999),
                'country' => 'DE',
                'status' => $this->getRandomStatus(),
                'joined_date' => $joinedDate->format('Y-m-d'),
                'created_at' => $joinedDate,
                'updated_at' => $joinedDate,
            ]);
        }
    }

    private function getRandomFirstName()
    {
        $names = ['Michael', 'Julia', 'Stefan', 'Maria', 'Andreas', 'Sarah', 'Thomas', 'Laura', 'Christian', 'Lisa',
                 'Daniel', 'Anna', 'Peter', 'Sophia', 'Markus', 'Emma', 'Lukas', 'Hannah', 'Felix', 'Lena'];
        return $names[array_rand($names)];
    }

    private function getRandomLastName()
    {
        $names = ['Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann',
                 'Bauer', 'Richter', 'Klein', 'Wolf', 'Schröder', 'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger'];
        return $names[array_rand($names)];
    }

    private function getRandomStatus()
    {
        $statuses = ['active', 'active', 'active', 'active', 'inactive', 'paused', 'overdue'];
        return $statuses[array_rand($statuses)];
    }
}
