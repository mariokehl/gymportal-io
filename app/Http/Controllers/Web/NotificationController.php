<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $notifications = NotificationRecipient::with(['notification'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications
        ]);
    }

    public function unread()
    {
        $user = auth()->user();

        $notifications = NotificationRecipient::with(['notification'])
            ->where('user_id', $user->id)
            ->unread()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($recipient) {
                return [
                    'id' => $recipient->id,
                    'title' => $recipient->notification->title,
                    'content' => $recipient->notification->content,
                    'type' => $recipient->notification->type,
                    'created_at' => $recipient->created_at->diffForHumans(),
                    'link' => $this->getNotificationLink($recipient->notification),
                    'is_read' => $recipient->is_read,
                ];
            });

        return response()->json($notifications);
    }

    public function markAsRead(NotificationRecipient $recipient)
    {
        if ($recipient->user_id !== auth()->id()) {
            abort(403);
        }

        $recipient->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        NotificationRecipient::where('user_id', auth()->id())
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json(['success' => true]);
    }

    private function getNotificationLink($notification)
    {
        // Beispiel-Logik für Links basierend auf Notification-Typ
        switch ($notification->type) {
            case 'member_registered':
                // Extrahiere Member-ID aus Content oder verwende eine andere Methode
                if (preg_match('/ID #(\d+)/', $notification->content, $matches)) {
                    return route('members.show', $matches[1]);
                }
                return route('members.index');

            case 'contract_expiring':
                return route('contracts.index');

            case 'payment_reminder':
                if (preg_match('/ID #(\d+)/', $notification->content, $matches)) {
                    return route('payments.show', $matches[1]);
                }
                return route('finances.index');

            default:
                return route('notifications.index');
        }
    }
}
