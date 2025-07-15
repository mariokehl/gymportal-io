<?php
// app/Http/Controllers/Web/DashboardController.php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Member::class);

        /** @var User $user */
        $user = Auth::user();

        // Get paginated members with search functionality
        $members = Member::query()
            ->when(request('search'), function ($query, $search) {
                $query->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->when(request('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->when(request('membership'), function ($query, $membership) {
                $query->where('membership_type', $membership);
            })
            ->where('gym_id', $user->current_gym_id)
            ->with('memberships.membershipPlan')
            ->with('checkIns')
            ->orderBy('created_at', 'desc')
            ->limit(10) // Limit for dashboard view
            ->get()
            ->map(function ($member) {
                $firstMembership = $member->memberships->first();

                if (!$firstMembership) {
                    return [
                        'id' => $member->id,
                        'initials' => $member->initials,
                        'name' => $member->full_name,
                        'email' => $member->email,
                        'membership' => 'Keine Mitgliedschaft',
                        'status' => $member->status
                    ];
                }

                return [
                    'id' => $member->id,
                    'initials' => $member->initials,
                    'name' => $member->full_name,
                    'email' => $member->email,
                    'membership' => $firstMembership->toArray()['membership_plan']['name'],
                    'status' => $member->status,
                    'last_check_in' => $member->last_check_in,
                ];
            });

        // Calculate statistics
        $totalMembers = Member::query()
            ->where('gym_id', $user->current_gym_id)
            ->count();
        $activeMembers = Member::active()
            ->where('gym_id', $user->current_gym_id)
            ->count();
        $newMembersThisMonth = Member::whereMonth('created_at', Carbon::now()->month)
                                   ->whereYear('created_at', Carbon::now()->year)
                                   ->count();
        //$expiringThisMonth = Member::expiringThisMonth()->count();
        //$monthlyRevenue = Member::active()->sum('monthly_fee');

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
                'value' => '14,250 €',
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

        return Inertia::render('Dashboard/Index', [
            'members' => $members,
            'stats' => $stats,
            'notifications' => $notifications,
            'totalMembers' => $totalMembers,
            'filters' => [
                'search' => request('search'),
                'status' => request('status'),
                'membership' => request('membership')
            ]
        ]);
    }
}
