<?php

namespace App\Providers;

use App\Services\WidgetService;
use Illuminate\Support\ServiceProvider;

/**
 * @deprecated Not used since v0.0.1; never referenced since it was introduced.
 */
class WidgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WidgetService::class);
    }
}
