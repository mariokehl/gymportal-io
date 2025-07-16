<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'admin'],
            ['name' => 'Gym Owner', 'slug' => 'owner'],
            ['name' => 'Staff', 'slug' => 'staff'],
            ['name' => 'Trainer', 'slug' => 'trainer'],
            ['name' => 'Member', 'slug' => 'member'],
        ];

        foreach ($roles as $role) {
            // Verwende updateOrInsert für automatische Duplikatsprüfung
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']], // Bedingung für die Suche
                [
                    'name' => $role['name'],
                    'slug' => $role['slug'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
    }
}
