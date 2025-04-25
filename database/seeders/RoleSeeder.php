<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        DB::table('roles')->insert([
            ['name' => 'Administrator', 'slug' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gym Owner', 'slug' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Staff', 'slug' => 'staff', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Trainer', 'slug' => 'trainer', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Member', 'slug' => 'member', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
