<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Events\NewNotificationEvent;
use App\Models\User;

class NotificationService
{
    public function createNotification(array $data)
    {
        $notification = Notification::create([
            'gym_id' => $data['gym_id'],
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'],
            'send_at' => $data['send_at'] ?? now(),
        ]);

        // Erstelle Empfänger für alle Benutzer des Gyms
        $users = User::where('gym_id', $data['gym_id'])->get();

        foreach ($users as $user) {
            $recipient = NotificationRecipient::create([
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'is_read' => false,
                'delivery_method' => 'app',
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Sende WebSocket-Event
            event(new NewNotificationEvent($recipient));
        }

        return $notification;
    }

    public function notifyMemberRegistered($member)
    {
        $this->createNotification([
            'gym_id' => $member->gym_id,
            'title' => 'Neues Mitglied registriert',
            'content' => "Neues Mitglied registriert: {$member->first_name} {$member->last_name}",
            'type' => 'member_registered'
        ]);
    }

    public function notifyContractsExpiring($gym, $count)
    {
        $this->createNotification([
            'gym_id' => $gym->id,
            'title' => 'Verträge laufen aus',
            'content' => "{$count} Verträge laufen diesen Monat aus",
            'type' => 'contract_expiring'
        ]);
    }

    public function notifyPaymentReminder($gym, $paymentId)
    {
        $this->createNotification([
            'gym_id' => $gym->id,
            'title' => 'Zahlungserinnerung versandt',
            'content' => "Zahlungserinnerung für ID #{$paymentId} versandt",
            'type' => 'payment_reminder'
        ]);
    }
}
