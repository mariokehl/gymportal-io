<?php

namespace App\Listeners;

use App\Events\MembershipActivated;
use App\Services\ContractService;
use Illuminate\Support\Facades\Log;

class GenerateMembershipContract
{
    public function __construct(
        private ContractService $contractService
    ) {}

    public function handle(MembershipActivated $event): void
    {
        $membership = $event->membership;
        $member = $membership->member;
        $gym = $member->gym;

        // Keinen Vertrag für Gratis-Testzeitraum-Mitgliedschaften erstellen
        if ($membership->is_free_trial) {
            return;
        }

        // Keinen Vertrag erstellen, wenn bereits einer vorhanden ist
        if ($membership->contract_file_path) {
            return;
        }

        if (!$gym->isOnlineContractEnabled()) {
            return;
        }

        $path = $this->contractService->generateContract($membership);

        if ($path) {
            Log::info('Vertrag bei Aktivierung generiert', [
                'membership_id' => $membership->id,
                'member_id' => $member->id,
            ]);
        }
    }
}
