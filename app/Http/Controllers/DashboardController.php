<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $members = [
            [
                'id' => 1,
                'name' => 'Max Mustermann',
                'membership' => 'Premium',
                'status' => 'Aktiv',
                'lastVisit' => '04.04.2025',
                'contractEnd' => '15.12.2025',
                'email' => 'max@example.com'
            ],
            [
                'id' => 2,
                'name' => 'Anna Schmidt',
                'membership' => 'Standard',
                'status' => 'Aktiv',
                'lastVisit' => '02.04.2025',
                'contractEnd' => '05.09.2025',
                'email' => 'anna@example.com'
            ],
            [
                'id' => 3,
                'name' => 'Felix Bauer',
                'membership' => 'Basic',
                'status' => 'Inaktiv',
                'lastVisit' => '15.03.2025',
                'contractEnd' => '01.05.2025',
                'email' => 'felix@example.com'
            ],
            [
                'id' => 4,
                'name' => 'Laura Müller',
                'membership' => 'Premium',
                'status' => 'Aktiv',
                'lastVisit' => '03.04.2025',
                'contractEnd' => '22.11.2025',
                'email' => 'laura@example.com'
            ]
        ];

        $stats = [
            [
                'title' => 'Aktive Mitglieder',
                'value' => 248,
                'change' => '+12%',
                'icon' => 'users'
            ],
            [
                'title' => 'Neue Verträge',
                'value' => 18,
                'change' => '+5%',
                'icon' => 'file-plus'
            ],
            [
                'title' => 'Monatsumsatz',
                'value' => '€14,250',
                'change' => '+8%',
                'icon' => 'dollar-sign'
            ],
            [
                'title' => 'Vertragserneuerungen',
                'value' => 12,
                'change' => '-3%',
                'icon' => 'bar-chart'
            ]
        ];

        $notifications = [
            [
                'id' => 1,
                'text' => 'Neues Mitglied registriert: Laura Müller',
                'time' => 'Heute'
            ],
            [
                'id' => 2,
                'text' => '5 Verträge laufen diesen Monat aus',
                'time' => 'Gestern'
            ],
            [
                'id' => 3,
                'text' => 'Zahlungserinnerung für ID #2458 versandt',
                'time' => 'Gestern'
            ]
        ];

        /** @var User $user */
        $user = Auth::user();
        $user->load('ownedGyms');

        return Inertia::render('Dashboard/Index', [
            'user' => $user,
            'members' => $members,
            'stats' => $stats,
            'notifications' => $notifications
        ]);
    }
}
