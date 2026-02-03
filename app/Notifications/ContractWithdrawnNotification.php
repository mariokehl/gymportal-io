<?php

namespace App\Notifications;

use App\Models\Member;
use App\Models\Membership;
use App\Models\Gym;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notification für Vertragswiderruf durch Mitglied (§ 356a BGB)
 *
 * Diese Notification wird ausgelöst, wenn ein Mitglied seinen Vertrag
 * selbstständig über die PWA widerruft.
 */
class ContractWithdrawnNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Member $member,
        public Membership $membership,
        public Gym $gym,
        public float $refundAmount = 0.0
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
        $refundText = $this->refundAmount > 0
            ? ' Erstattung: ' . number_format($this->refundAmount, 2, ',', '.') . ' €'
            : '';

        return [
            'type' => 'contract_withdrawn',
            'title' => 'Vertrag widerrufen',
            'message' => "{$this->member->first_name} {$this->member->last_name} hat den Vertrag \"{$this->membership->membershipPlan?->name}\" widerrufen.{$refundText}",
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
                'withdrawn_at' => $this->membership->withdrawn_at?->format('d.m.Y H:i'),
            ],
            'refund_amount' => $this->refundAmount,
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
        $refundText = $this->refundAmount > 0
            ? ' (' . number_format($this->refundAmount, 2, ',', '.') . ' € Erstattung)'
            : '';

        return [
            'type' => 'contract_withdrawn',
            'title' => 'Vertrag widerrufen',
            'message' => "{$this->member->first_name} {$this->member->last_name} hat einen Vertrag widerrufen.{$refundText}",
            'member_id' => $this->member->id,
            'member_number' => $this->member->member_number,
            'membership_id' => $this->membership->id,
            'gym_id' => $this->gym->id,
        ];
    }
}
