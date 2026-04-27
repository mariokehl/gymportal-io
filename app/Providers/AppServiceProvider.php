<?php

namespace App\Providers;

use App\Mail\Dispatching\MemberMailDispatcher;
use App\Mail\Policies\MissingEmailPolicy;
use App\Mail\Policies\SyntheticEmailPolicy;
use App\Services\EmailTemplateService;
use App\Services\SchedulerHealthCheckService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Reihenfolge der Mail-Policies.
     *
     * Neue Regel? Klasse anlegen, implementiert {@see MemberMailPolicy},
     * hier eintragen. Kein Code-Change am Dispatcher nötig.
     */
    private const MEMBER_MAIL_POLICIES = [
        MissingEmailPolicy::class,
        SyntheticEmailPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SchedulerHealthCheckService::class);
        $this->app->bind(EmailTemplateService::class, function ($app) {
            return new EmailTemplateService();
        });

        $this->app->singleton(MemberMailDispatcher::class, function ($app) {
            $policies = array_map(
                fn (string $class) => $app->make($class),
                self::MEMBER_MAIL_POLICIES,
            );

            return new MemberMailDispatcher($policies);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
