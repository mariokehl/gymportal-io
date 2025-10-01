<?php

namespace App\Providers;

use App\Events\MemberRegistered;
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
    ];

    public function boot(): void
    {
        //
    }
}
