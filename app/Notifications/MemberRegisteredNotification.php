<?php

namespace App\Notifications;

use App\Models\FraudCheck;
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
        $fraudCheck = $this->getFraudCheck();
        $isFlagged = $fraudCheck !== null;

        $data = [
            'type' => 'member_registered',
            'title' => $isFlagged ? 'Verdächtige Registrierung' : 'Neues Mitglied registriert',
            'message' => $isFlagged
                ? "{$this->member->first_name} {$this->member->last_name} wurde als verdächtig eingestuft (Score: {$fraudCheck->fraud_score}/100). Übereinstimmungen: "
                    . collect($fraudCheck->matched_fields)->except('_combination_bonus')->keys()->join(', ')
                : "{$this->member->first_name} {$this->member->last_name} hat sich über {$this->registrationSource} registriert.",
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

        if ($isFlagged) {
            $data['fraud'] = [
                'score' => $fraudCheck->fraud_score,
                'action' => $fraudCheck->action,
                'matched_fields' => $fraudCheck->matched_fields,
            ];
        }

        return $data;
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toBroadcast(object $notifiable): array
    {
        $fraudCheck = $this->getFraudCheck();
        $isFlagged = $fraudCheck !== null;

        return [
            'type' => 'member_registered',
            'title' => $isFlagged ? 'Verdächtige Registrierung' : 'Neues Mitglied registriert',
            'message' => $isFlagged
                ? "{$this->member->first_name} {$this->member->last_name} – Score: {$fraudCheck->fraud_score}/100"
                : "{$this->member->first_name} {$this->member->last_name} hat sich über {$this->registrationSource} registriert.",
            'member_id' => $this->member->id,
            'member_number' => $this->member->member_number,
            'gym_id' => $this->gym->id,
        ];
    }

    private function getFraudCheck(): ?FraudCheck
    {
        return FraudCheck::where('member_id', $this->member->id)
            ->where('action', '!=', 'allowed')
            ->latest('checked_at')
            ->first();
    }
}
