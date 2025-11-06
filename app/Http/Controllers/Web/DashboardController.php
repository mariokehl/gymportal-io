<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    protected MemberService $memberService;

    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
    }

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
                    'membership' => $firstMembership->toArray()['membership_plan']['name'] ?? 'Gelöschter Vertrag',
                    'status' => $member->status,
                    'last_check_in' => $member->last_check_in,
                ];
            });

        // Calculate statistics
        $stats = $this->memberService->getDashboardStats($user->current_gym_id);

        // Get latest notifications
        $notifications = $user->notifications()
            ->limit(5)
            ->get()
            ->map(function (DatabaseNotification $notification) {
                $data = $notification->data;
                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? 'Benachrichtigung',
                    'message' => $data['message'] ?? '',
                    'type' => $data['type'] ?? 'system',
                    'created_at' => $notification->created_at->diffForHumans(),
                    'read_at' => $notification->read_at,
                ];
            });

        return Inertia::render('Dashboard/Index', [
            'user' => $user,
            'members' => $members,
            'stats' => $stats,
            'notifications' => $notifications,
            'totalMembers' => $stats['detailed_stats']['total_members'],
            'filters' => [
                'search' => request('search'),
                'status' => request('status'),
                'membership' => request('membership')
            ]
        ]);
    }
}
