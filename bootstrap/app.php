<?php

use App\Http\Middleware\AuthenticateScanner;
use App\Http\Middleware\BasicAuthMiddleware;
use App\Http\Middleware\CheckIfUserBlocked;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PaddleIpMiddleware;
use App\Http\Middleware\WidgetAuthMiddleware;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            SubstituteBindings::class,
        ]);
        $middleware->group('widget', [
            WidgetAuthMiddleware::class,
            'throttle:api',
            SubstituteBindings::class,
        ]);
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'widget.auth' => WidgetAuthMiddleware::class,
            'subscription' => CheckSubscription::class,
            'paddleIp' => PaddleIpMiddleware::class,
            'scanner.auth' => AuthenticateScanner::class,
            'blocked.check' => CheckIfUserBlocked::class,
            'basic.auth' => BasicAuthMiddleware::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/pwa/*',
            'api/scanner/*',
            'api/widget/*',
            'billing/webhook/paddle',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
