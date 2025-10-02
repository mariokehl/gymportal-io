<?php

namespace App\Providers;

use App\Events\MemberRegistered;
use App\Events\MollieMandateCreated;
use App\Listeners\ActivateMolliePaymentMethod;
use App\Listeners\HandleMolliePaymentMethod;
use App\Listeners\SendMemberRegisteredNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MemberRegistered::class => [
            HandleMolliePaymentMethod::class,
            SendMemberRegisteredNotification::class,
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
