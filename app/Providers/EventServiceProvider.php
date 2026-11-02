<?php

namespace App\Providers;

use App\Events\ContractCancelled;
use App\Events\ContractWithdrawn;
use App\Events\MemberRegistered;
use App\Events\MembershipActivated;
use App\Events\MollieMandateCreated;
use App\Listeners\ActivateMolliePaymentMethod;
use App\Listeners\GenerateMembershipContract;
use App\Listeners\HandleMolliePaymentMethod;
use App\Listeners\SendContractCancelledNotification;
use App\Listeners\SendContractWithdrawnNotification;
use App\Listeners\SendMemberRegisteredNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * @deprecated Not used since v0.0.13; never referenced since it was introduced.
 */
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ContractCancelled::class => [
            SendContractCancelledNotification::class,
        ],
        ContractWithdrawn::class => [
            SendContractWithdrawnNotification::class,
        ],
        MemberRegistered::class => [
            HandleMolliePaymentMethod::class,
            SendMemberRegisteredNotification::class,
        ],
        MembershipActivated::class => [
            GenerateMembershipContract::class,
        ],
        MollieMandateCreated::class => [
            ActivateMolliePaymentMethod::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
