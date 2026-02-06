<?php

namespace App\Notifications;

use App\Models\Member;
use App\Models\Gym;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Member $member,
        public Gym $gym,
        public array $paymentData = []
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $amount = isset($this->paymentData['amount'])
            ? number_format((float) $this->paymentData['amount'], 2, ',', '.') . ' EUR'
            : 'unbekannt';

        return [
            'type' => 'payment_failed',
            'title' => 'Zahlung fehlgeschlagen',
            'message' => "Zahlung von {$this->member->first_name} {$this->member->last_name} ist fehlgeschlagen. Mitgliedsstatus wurde auf \"Überfällig\" geändert.",
            'member' => [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'first_name' => $this->member->first_name,
                'last_name' => $this->member->last_name,
                'email' => $this->member->email,
                'status' => $this->member->status,
            ],
            'payment' => [
                'amount' => $amount,
                'mollie_payment_id' => $this->paymentData['mollie_payment_id'] ?? null,
                'payment_method' => $this->paymentData['payment_method'] ?? null,
            ],
            'gym_id' => $this->gym->id,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toBroadcast(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'title' => 'Zahlung fehlgeschlagen',
            'message' => "Zahlung von {$this->member->first_name} {$this->member->last_name} fehlgeschlagen",
            'member_id' => $this->member->id,
            'member_number' => $this->member->member_number,
            'gym_id' => $this->gym->id,
        ];
    }
}
