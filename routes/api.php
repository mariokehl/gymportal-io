<?php

use App\Http\Controllers\Api\V1\MollieSetupController;
use App\Http\Controllers\Api\WidgetController;
use App\Http\Controllers\Web\NotificationController;
use App\Services\MollieService;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
| API Routes
*/

Route::prefix('v1')->name('v1.')->group(function () {
    Route::middleware([
        'auth:sanctum',
    ])->group(static function (): void {
        Route::name('notifications.')->group(static function (): void {
            Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('unread');
            Route::post('/notifications/{recipient}/mark-read', [NotificationController::class, 'markAsRead'])->name('mark-read');
            Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        });
        Route::name('mollie.')->group(static function (): void {
            Route::post('/mollie/validate-credentials', [MollieSetupController::class, 'validateCredentials'])->name('validate-credentials');
            Route::post('/mollie/save-config', [MollieSetupController::class, 'saveConfiguration'])->name('save-config');
            Route::post('/mollie/test-payment', [MollieSetupController::class, 'testIntegration'])->name('test-integration');
            Route::post('/mollie/webhook-status', [MollieSetupController::class, 'checkWebhookStatus'])->name('check-webhook-status');
        });
    });

    // Public routes
    Route::name('public.')->prefix('/public')->group(static function (): void {
        // Mollie webhook
        //Route::post('/webhooks/mollie/{organization}', [MollieService::class, 'handleWebhook'])->name('mollie.webhook');
        Route::post('/mollie/webhook', [WidgetController::class, 'handleMollieWebhook'])->name('mollie.webhook');
    });

    /*
    Route::middleware('auth:sanctum')->get('/debug', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'token' => $request->bearerToken(),
        ]);
    });
    */
});


/*
| Widget API Routes
*/

Route::group(['prefix' => 'widget', 'middleware' => ['api', 'widget.auth']], function () {
    Route::get('/markup/plans', [WidgetController::class, 'getPlansMarkup']);
    Route::get('/markup/form', [WidgetController::class, 'getFormMarkup']);
    Route::get('/markup/checkout', [WidgetController::class, 'getCheckoutMarkup']);
    Route::post('/save-form-data', [WidgetController::class, 'saveFormData']);
    Route::post('/contracts', [WidgetController::class, 'createContract']);
    Route::post('/analytics', [WidgetController::class, 'trackAnalytics']);
    Route::post('/mollie/check-status', [WidgetController::class, 'checkMolliePaymentStatus']);
});

// Mollie Public Routes
Route::prefix('widget')->group(function () {
    Route::get('/mollie/return/{gym}/{session}', [WidgetController::class, 'handleMollieReturn'])->name('widget.mollie.return'); // Mollie Return-URL (für Browserweiterleitung)
});

// Widget-Middleware für Authentifizierung
//Route::middleware('widget.auth', function () {
    // Weitere geschützte Routes
//});

/**
 * Fallback routes, to prevent a rendered HTML page in /api/* routes
 * The / route is also included since the fallback is not triggered on the root route
 */
Route::get('/', function (): void {
    throw new NotFoundHttpException('API resource not found');
});
Route::fallback(function (): void {
    throw new NotFoundHttpException('API resource not found');
});
