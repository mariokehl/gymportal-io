<?php

namespace App\Listeners;

use App\Events\ContractWithdrawn;
use App\Notifications\Concerns\NotifiesGymUsers;
use App\Notifications\ContractWithdrawnNotification;
use Exception;
use Illuminate\Support\Facades\Log;

class SendContractWithdrawnNotification
{
    use NotifiesGymUsers;

    public function handle(ContractWithdrawn $event): void
    {
        try {
            $this->notifyGymUsers(
                $event->gym,
                new ContractWithdrawnNotification($event->member, $event->membership, $event->gym, $event->refundAmount),
                [
                    'member_id' => $event->member->id,
                    'membership_id' => $event->membership->id,
                ]
            );
        } catch (Exception $e) {
            Log::error('Failed to send contract withdrawal notification', [
                'member_id' => $event->member->id,
                'membership_id' => $event->membership->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
