<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            GymSeeder::class,
            GymUserSeeder::class,
            MembershipPlanSeeder::class,
            MemberSeeder::class,
            MemberStatusHistorySeeder::class,
            MembershipSeeder::class,
            PaymentMethodSeeder::class,
            PaymentSeeder::class,
            CourseSeeder::class,
            CourseScheduleSeeder::class,
            CourseBookingSeeder::class,
            CheckInSeeder::class,
            NotificationSeeder::class,
            NotificationRecipientSeeder::class,
            UpdateUsersCurrentGymSeeder::class,
        ]);

        Schema::enableForeignKeyConstraints();
    }
}
