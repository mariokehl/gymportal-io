<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MembershipPlanSeeder extends Seeder
{
    public function run()
    {
        // FitZone Membership Plans
        DB::table('membership_plans')->insert([
            'gym_id' => 1,
            'name' => 'Basic',
            'description' => 'Grundlegende Mitgliedschaft mit Zugang zu allen Geräten',
            'price' => 29.99,
            'billing_cycle' => 'monthly',
            'commitment_months' => 12,
            'cancellation_period_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('membership_plans')->insert([
            'gym_id' => 1,
            'name' => 'Premium',
            'description' => 'Premium-Mitgliedschaft mit Zugang zu allen Geräten und Kursen',
            'price' => 49.99,
            'billing_cycle' => 'monthly',
            'commitment_months' => 12,
            'cancellation_period_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('membership_plans')->insert([
            'gym_id' => 1,
            'name' => 'Jahres-Flat',
            'description' => 'Jährliche Mitgliedschaft mit Rabatt',
            'price' => 499.99,
            'billing_cycle' => 'yearly',
            'commitment_months' => 12,
            'cancellation_period_days' => 60,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // PowerFit Membership Plans
        DB::table('membership_plans')->insert([
            'gym_id' => 2,
            'name' => 'Basis',
            'description' => 'Basis-Mitgliedschaft für Krafttraining',
            'price' => 34.99,
            'billing_cycle' => 'monthly',
            'commitment_months' => 6,
            'cancellation_period_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('membership_plans')->insert([
            'gym_id' => 2,
            'name' => 'Power Plus',
            'description' => 'Vollständige Mitgliedschaft mit Personal Training',
            'price' => 59.99,
            'billing_cycle' => 'monthly',
            'commitment_months' => 6,
            'cancellation_period_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
