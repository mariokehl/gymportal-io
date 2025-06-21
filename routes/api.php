<?php

use App\Http\Controllers\Api\V1\MollieSetupController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Illuminate\Http\Request;

/*
| API Routes
*/

Route::prefix('v1')->name('v1.')->group(function () {
    Route::middleware([
        'auth:sanctum',
    ])->group(static function (): void {
        // Mollie payment routes
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
        Route::get('/webhooks/mollie/{organization}', [MollieSetupController::class, 'validateCredentials'])->name('mollie.webhook');
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
