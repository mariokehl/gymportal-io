<?php

namespace App\Listeners;

use App\Events\ContractCancelled;
use App\Notifications\Concerns\NotifiesGymUsers;
use App\Notifications\ContractCancelledNotification;
use Exception;
use Illuminate\Support\Facades\Log;

class SendContractCancelledNotification
{
    use NotifiesGymUsers;

    public function handle(ContractCancelled $event): void
    {
        try {
            $this->notifyGymUsers(
                $event->gym,
                new ContractCancelledNotification($event->member, $event->membership, $event->gym),
                [
                    'member_id' => $event->member->id,
                    'membership_id' => $event->membership->id,
                ]
            );
        } catch (Exception $e) {
            Log::error('Failed to send contract cancelled notification', [
                'member_id' => $event->member->id,
                'membership_id' => $event->membership->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
