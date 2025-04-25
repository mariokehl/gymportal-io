<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    public function run()
    {
        // Notifications for FitZone
        $fitZoneNotifications = [
            [
                'title' => 'Willkommen bei FitZone!',
                'content' => 'Herzlich willkommen in unserem Fitnessstudio. Wir freuen uns, dass du dich für FitZone entschieden hast!',
                'type' => 'announcement',
                'send_at' => Carbon::now()->subDays(15),
            ],
            [
                'title' => 'Neue Yoga-Kurse',
                'content' => 'Ab nächster Woche bieten wir neue Yoga-Kurse an. Schau in unserem Kursplan nach und melde dich an!',
                'type' => 'announcement',
                'send_at' => Carbon::now()->subDays(7),
            ],
            [
                'title' => 'Sommerpause',
                'content' => 'Wir informieren dich, dass unser Studio vom 15. bis 20. August wegen Renovierungsarbeiten geschlossen sein wird.',
                'type' => 'system',
                'send_at' => Carbon::now()->subDays(3),
            ],
        ];

        foreach ($fitZoneNotifications as $notification) {
            DB::table('notifications')->insert([
                'gym_id' => 1,
                'title' => $notification['title'],
                'content' => $notification['content'],
                'type' => $notification['type'],
                'send_at' => $notification['send_at'],
                'created_at' => $notification['send_at'],
                'updated_at' => $notification['send_at'],
            ]);
        }

        // Notifications for PowerFit
        $powerFitNotifications = [
            [
                'title' => 'Willkommen bei PowerFit!',
                'content' => 'Herzlich willkommen in unserem Krafttraining-Studio. Wir freuen uns, dass du dich für PowerFit entschieden hast!',
                'type' => 'announcement',
                'send_at' => Carbon::now()->subDays(10),
            ],
            [
                'title' => 'Neue Trainingsgeräte',
                'content' => 'Wir haben neue Trainingsgeräte angeschafft. Komm vorbei und probiere sie aus!',
                'type' => 'announcement',
                'send_at' => Carbon::now()->subDays(5),
            ],
        ];

        foreach ($powerFitNotifications as $notification) {
            DB::table('notifications')->insert([
                'gym_id' => 2,
                'title' => $notification['title'],
                'content' => $notification['content'],
                'type' => $notification['type'],
                'send_at' => $notification['send_at'],
                'created_at' => $notification['send_at'],
                'updated_at' => $notification['send_at'],
            ]);
        }
    }
}
