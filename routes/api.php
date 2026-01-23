<?php

use App\Http\Controllers\Api\MollieWebhookController;
use App\Http\Controllers\Api\V1\MollieSetupController;
use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\WidgetController;
use App\Http\Controllers\Pwa\AuthController;
use App\Http\Controllers\Pwa\CheckInController;
use App\Http\Controllers\Pwa\GymController;
use App\Http\Controllers\Pwa\MemberController;
use App\Http\Controllers\Web\NotificationController;
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
            Route::post('/notifications/{notification}/mark-read', [NotificationController::class, 'markAsRead'])->name('mark-read');
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
        Route::post('/mollie/webhook', [MollieWebhookController::class, 'handle'])->name('mollie.webhook');
    });
});

/*
| PWA Routes - Mit separatem Guard
*/
Route::group(['prefix' => 'pwa'], function () {
    Route::prefix('gyms')->group(function () {
        Route::get('/', [GymController::class, 'index']);
        Route::get('{slug}', [GymController::class, 'show']);
        Route::get('{slug}/theme', [GymController::class, 'theme']);
        Route::get('{slug}/manifest', [GymController::class, 'manifest']);
        Route::get('{slug}/related', [GymController::class, 'related']);
    });
    Route::prefix('auth')->group(function () {
        Route::post('send-code', [AuthController::class, 'sendLoginCode']);
        Route::post('verify-code', [AuthController::class, 'verifyCode']);
        Route::post('link-contract-anonymous', [AuthController::class, 'linkContractAnonymous']);
    });
    Route::middleware(['auth:member-pwa'])->prefix('member')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [MemberController::class, 'profile']);
        Route::get('contract', [MemberController::class, 'contract']);
        Route::get('gyms', [MemberController::class, 'gyms']);
        Route::prefix('checkin')->group(function () {
            Route::get('latest', [CheckInController::class, 'getLatest']);
            Route::post('{id}/end', [CheckInController::class, 'endCheckin'])->where('id', '[0-9]+');
        });

        // Auth upgrade routes (require anonymous or full session)
        Route::post('upgrade-session', [AuthController::class, 'upgradeSession']);

        // Routes requiring full session verification
        Route::middleware(['full.session'])->group(function () {
            Route::put('profile', [MemberController::class, 'updateProfile']);
            Route::put('contract', [MemberController::class, 'updateContract']);
            Route::delete('contract', [MemberController::class, 'cancelContract']);
            Route::get('qr-code', [MemberController::class, 'generateQrCode']);
        });
    });
});

/*
| Scanner Routes
*/
Route::prefix('scanner')->group(function () {
    Route::middleware(['scanner.auth'])->group(function () {
        Route::get('verify-membership', [ScannerController::class, 'verifyMembership']);
        //Route::post('validate', [ScannerController::class, 'validateAccess']);
        //Route::post('test', [ScannerController::class, 'validateAccess']); // Alias
    });
});

// API Routes for scanner devices
//Route::prefix('api/v1')->group(function () {
//    Route::post('/access/validate', [MemberAccessController::class, 'validateAccess'])
//        ->middleware('throttle:60,1')
//        ->name('api.access.validate');
//});

/*
| Widget API Routes
*/
Route::group(['prefix' => 'widget', 'middleware' => ['widget']], function () {
    Route::get('/markup/plans', [WidgetController::class, 'getPlansMarkup']);
    Route::get('/markup/form', [WidgetController::class, 'getFormMarkup']);
    Route::get('/markup/checkout', [WidgetController::class, 'getCheckoutMarkup']);
    Route::post('/save-form-data', [WidgetController::class, 'saveFormData']);
    Route::post('/contracts', [WidgetController::class, 'createContract']);
    Route::post('/analytics', [WidgetController::class, 'trackAnalytics']);
    Route::post('/mollie/check-status', [WidgetController::class, 'checkMolliePaymentStatus']);
});
Route::prefix('widget')->group(function () {
    Route::get('/mollie/return/{gym}/{session}', [WidgetController::class, 'handleMollieReturn'])->name('widget.mollie.return');
});

/**
 * Fallback routes
 */
Route::get('/', function (): void {
    throw new NotFoundHttpException('API resource not found');
});
Route::fallback(function (): void {
    throw new NotFoundHttpException('API resource not found');
});
