<?php

namespace App\Notifications;

use App\Models\Member;
use App\Models\Membership;
use App\Models\Gym;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MemberRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Member $member,
        public Membership $membership,
        public Gym $gym,
        public string $registrationSource = 'unknown'
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
        return [
            'type' => 'member_registered',
            'title' => 'Neues Mitglied registriert',
            'message' => "{$this->member->first_name} {$this->member->last_name} hat sich über {$this->registrationSource} registriert.",
            'member' => [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'first_name' => $this->member->first_name,
                'last_name' => $this->member->last_name,
                'email' => $this->member->email,
                'status' => $this->member->status,
            ],
            'membership' => [
                'id' => $this->membership->id,
                'status' => $this->membership->status,
                'start_date' => $this->membership->start_date?->format('d.m.Y'),
            ],
            'gym_id' => $this->gym->id,
            'registration_source' => $this->registrationSource,
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
            'type' => 'member_registered',
            'title' => 'Neues Mitglied registriert',
            'message' => "{$this->member->first_name} {$this->member->last_name} hat sich über {$this->registrationSource} registriert.",
            'member_id' => $this->member->id,
            'member_number' => $this->member->member_number,
            'gym_id' => $this->gym->id,
        ];
    }
}
