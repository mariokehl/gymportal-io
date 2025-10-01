<?php

namespace App\Events;

use App\Models\NotificationRecipient;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @deprecated since v0.0.29
 */
class NewNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notificationRecipient;
    public $notificationData;

    public function __construct(NotificationRecipient $notificationRecipient)
    {
        $this->notificationRecipient = $notificationRecipient;
        $this->notificationData = [
            'id' => $notificationRecipient->id,
            'title' => $notificationRecipient->notification->title,
            'content' => $notificationRecipient->notification->content,
            'type' => $notificationRecipient->notification->type,
            'created_at' => $notificationRecipient->created_at->diffForHumans(),
            'link' => $this->getNotificationLink($notificationRecipient->notification),
            'is_read' => $notificationRecipient->is_read,
        ];
    }

    public function broadcastOn()
    {
        return new PrivateChannel('notifications.' . $this->notificationRecipient->user_id);
    }

    public function broadcastWith()
    {
        return [
            'notification' => $this->notificationData
        ];
    }

    private function getNotificationLink($notification)
    {
        // Gleiche Logik wie im Controller
        switch ($notification->type) {
            case 'member_registered':
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
