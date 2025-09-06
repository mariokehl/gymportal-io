<?php

namespace App\Providers;

use App\Events\MemberRegistered;
use App\Listeners\HandleMolliePaymentMethod;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MemberRegistered::class => [
            HandleMolliePaymentMethod::class
        ],
    ];

    public function boot(): void
    {
        //
    }
}
