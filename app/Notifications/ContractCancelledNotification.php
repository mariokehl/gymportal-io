<?php

namespace App\Notifications;

use App\Models\Member;
use App\Models\Membership;
use App\Models\Gym;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification für Vertragskündigung durch Mitglied
 *
 * Diese Notification wird ausgelöst, wenn ein Mitglied seinen Vertrag
 * selbstständig über die PWA kündigt.
 */
class ContractCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Member $member,
        public Membership $membership,
        public Gym $gym,
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
        $cancellationDate = $this->membership->cancellation_date?->format('d.m.Y');
        $cancellationText = $cancellationDate
            ? " Vertragsende: {$cancellationDate}"
            : '';

        return [
            'type' => 'contract_cancelled',
            'title' => 'Vertrag gekündigt',
            'message' => "{$this->member->first_name} {$this->member->last_name} hat den Vertrag \"{$this->membership->membershipPlan?->name}\" gekündigt.{$cancellationText}",
            'member' => [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'first_name' => $this->member->first_name,
                'last_name' => $this->member->last_name,
                'email' => $this->member->email,
            ],
            'membership' => [
                'id' => $this->membership->id,
                'plan_name' => $this->membership->membershipPlan?->name,
                'start_date' => $this->membership->start_date?->format('d.m.Y'),
                'cancellation_date' => $cancellationDate,
            ],
            'gym_id' => $this->gym->id,
            'source' => 'pwa',
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
        $cancellationDate = $this->membership->cancellation_date?->format('d.m.Y');
        $cancellationText = $cancellationDate
            ? " (Vertragsende: {$cancellationDate})"
            : '';

        return [
            'type' => 'contract_cancelled',
            'title' => 'Vertrag gekündigt',
            'message' => "{$this->member->first_name} {$this->member->last_name} hat einen Vertrag gekündigt.{$cancellationText}",
            'member_id' => $this->member->id,
            'member_number' => $this->member->member_number,
            'membership_id' => $this->membership->id,
            'gym_id' => $this->gym->id,
        ];
    }
}
