<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        $notifications = $user->notifications()
            ->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications
        ]);
    }

    public function unread()
    {
        /** @var User $user */
        $user = Auth::user();

        $notifications = $user->unreadNotifications()
            ->limit(10)
            ->get()
            ->map(function (DatabaseNotification $notification) {
                $data = $notification->data;
                return [
                    'id' => $notification->id,
                    'title' => $data['title'] ?? 'Benachrichtigung',
                    'message' => $data['message'] ?? '',
                    'type' => $data['type'] ?? 'system',
                    'created_at' => $notification->created_at->diffForHumans(),
                    'link' => $this->getNotificationLink($notification),
                    'read_at' => $notification->read_at,
                ];
            });

        return response()->json($notifications);
    }

    public function markAsRead(DatabaseNotification $notification)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($notification->notifiable_id !== $user->id) {
            abort(403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        /** @var User $user */
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    private function getNotificationLink(DatabaseNotification $notification)
    {
        $data = $notification->data;
        $type = $data['type'] ?? null;

        // Handle different notification types
        switch ($type) {
            case 'member_registered':
                $memberId = $data['member']['id'] ?? null;
                if ($memberId && Route::has('members.show')) {
                    return route('members.show', $memberId);
                }
                if (Route::has('members.index')) {
                    return route('members.index');
                }
                break;

            case 'contract_expiring':
                if (Route::has('contracts.index')) {
                    return route('contracts.index');
                }
                break;

            case 'payment_failed':
                if (Route::has('finances.index')) {
                    return route('finances.index');
                }
                break;

            default:
                if (Route::has('notifications.index')) {
                    return route('notifications.index');
                }
        }

        return null;
    }
}
