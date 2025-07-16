<?php

namespace App\Providers;

use App\Services\WidgetService;
use Illuminate\Support\ServiceProvider;

class WidgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WidgetService::class);
    }
}
