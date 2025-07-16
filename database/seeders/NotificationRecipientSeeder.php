<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationRecipientSeeder extends Seeder
{
    public function run()
    {
        // Get all notifications
        $notifications = DB::table('notifications')->get();

        foreach ($notifications as $notification) {
            // Get members of this gym
            $members = DB::table('members')
                ->where('gym_id', $notification->gym_id)
                ->get();

            foreach ($members as $member) {
                DB::table('notification_recipients')->insert([
                    'notification_id' => $notification->id,
                    'member_id' => $member->id,
                    'is_read' => rand(0, 1),
                    'read_at' => rand(0, 1) ? $notification->send_at : null,
                    'delivery_method' => 'app',
                    'status' => 'sent',
                    'sent_at' => $notification->send_at,
                    'created_at' => $notification->send_at,
                    'updated_at' => $notification->send_at,
                ]);
            }
        }
    }
}
