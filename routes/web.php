<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FinancesController;
use App\Http\Controllers\Web\GymController;
use App\Http\Controllers\Web\MemberAccessController;
use App\Http\Controllers\Web\MemberController;
use App\Http\Controllers\Web\MemberPaymentController;
use App\Http\Controllers\Web\MembershipController;
use App\Http\Controllers\Web\MembershipPlanController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\PaymentMethodController;
use App\Http\Controllers\Web\PaymentReturnController;
use App\Http\Controllers\Web\AccessControlController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\DataTransferController;
use App\Http\Controllers\Web\MemberDocumentController;
use App\Http\Controllers\Web\Settings\EmailTemplateController;
use App\Http\Controllers\Web\Settings\PaymentMethodsController;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
| Web Routes
*/

// Public routes
Route::get('/', function () {
    return redirect('/login');
});

// Payment return route (public, no auth required)
Route::get('/payment/return/{organization}', PaymentReturnController::class)->name('payment.return');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    // Password Reset Routes
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

// Blocked user route (accessible without auth)
Route::get('/blocked', [AuthController::class, 'blocked'])->name('blocked');

// Email verification
Route::middleware(['auth', 'blocked.check'])->group(function () {
    Route::get('/email/verify', [AuthController::class, 'showVerifyEmail'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])->middleware('throttle:6,1')->name('verification.send');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Billing-Routen
Route::middleware(['auth', 'verified', 'blocked.check'])->group(function () {
    // Billing Management
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/subscribe', [BillingController::class, 'subscribeToProfessional'])->name('billing.subscribe');
    Route::post('/billing/cancel', [BillingController::class, 'cancelSubscription'])->name('billing.cancel');
});

// Paddle Webhook (ohne Auth-Middleware, aber mit IP-Adresse)
Route::post('/billing/webhook/paddle', [BillingController::class, 'paddleWebhook'])->name('billing.webhook')->middleware('paddleIp');

// Protected routes
Route::middleware(['auth:web', 'verified', 'subscription', 'blocked.check'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/members/search', [MemberController::class, 'search'])->name('members.search');
    Route::post('/members/check-email', [MemberController::class, 'checkEmail'])->name('members.check-email');
    Route::post('/members/check-member-number', [MemberController::class, 'checkMemberNumber'])->name('members.check-member-number');
    Route::resource('members', MemberController::class);
    Route::put('/members/{member}/update-status', [MemberController::class, 'updateStatus'])->name('members.update-status');
    Route::post('/members/{member}/send-welcome', [MemberController::class, 'sendWelcome'])->name('members.send-welcome');
    Route::post('/members/{member}/toggle-age-verification', [MemberController::class, 'toggleAgeVerification'])->name('members.toggle-age-verification');
    Route::post('/members/{member}/toggle-guest-access', [MemberController::class, 'toggleGuestAccess'])->name('members.toggle-guest-access');
    Route::post('/members/{member}/memberships', [MemberController::class, 'storeMembership'])->name('members.memberships.store');
    Route::post('/members/{member}/memberships/free-period', [MembershipController::class, 'storeFreePeriod'])->name('members.memberships.store-free-period');
    Route::prefix('members/{member}/memberships/{membership}')->group(function () {
        Route::put('/activate', [MembershipController::class, 'activate'])->name('members.memberships.activate');
        Route::put('/pause', [MembershipController::class, 'pause'])->name('members.memberships.pause');
        Route::put('/resume', [MembershipController::class, 'resume'])->name('members.memberships.resume');
        Route::put('/cancel', [MembershipController::class, 'cancel'])->name('members.memberships.cancel');
        Route::put('/revoke-cancellation', [MembershipController::class, 'revokeCancellation'])->name('members.memberships.revoke-cancellation');
        Route::put('/abort', [MembershipController::class, 'abort'])->name('members.memberships.abort');
        Route::put('/withdraw', [MembershipController::class, 'withdraw'])->name('members.memberships.withdraw');
    });
    Route::prefix('members/{member}/payment-methods')->name('members.payment-methods.')->group(function () {
        Route::post('/', [PaymentMethodController::class, 'store'])->name('store');
        Route::put('/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('update');
        Route::put('/{paymentMethod}/set-default', [PaymentMethodController::class, 'setAsDefault'])->name('set-default');
        Route::put('/{paymentMethod}/deactivate', [PaymentMethodController::class, 'deactivate'])->name('deactivate');
        // Neue SEPA-Mandat Routen
        Route::put('/{paymentMethod}/mark-signed', [PaymentMethodController::class, 'markSepaMandateAsSigned'])->name('mark-signed');
        Route::put('/{paymentMethod}/activate-mandate', [PaymentMethodController::class, 'activateSepaMandate'])->name('activate-mandate');
    });
    Route::prefix('members/{member}/payments')->name('members.payments.')->group(function () {
        Route::post('/', [MemberPaymentController::class, 'store'])->name('store');
        Route::post('/{payment}/execute', [MemberPaymentController::class, 'execute'])->name('execute');
        Route::post('/execute-batch', [MemberPaymentController::class, 'executeBatch'])->name('execute-batch');
        Route::get('/{payment}/invoice', [MemberPaymentController::class, 'invoice'])->name('invoice');
    });
    Route::prefix('members/{member}/documents')->name('members.documents.')->group(function () {
        Route::get('/', [MemberDocumentController::class, 'index'])->name('index');
        Route::get('/{membership}/download', [MemberDocumentController::class, 'download'])->name('download');
        Route::post('/{membership}/generate', [MemberDocumentController::class, 'generateContract'])->name('generate');
    });
    Route::prefix('members/{member}/access')->name('members.access.')->group(function () {
        Route::put('/', [MemberAccessController::class, 'update'])->name('update');
        Route::post('/invalidate-qr', [MemberAccessController::class, 'invalidateQr'])->name('invalidate-qr');
        Route::post('/send-app-link', [MemberAccessController::class, 'sendAppLink'])->name('send-app-link');
        Route::get('/logs', [MemberAccessController::class, 'logs'])->name('logs');
        Route::post('/consume-credit', [MemberAccessController::class, 'consumeCredit'])->name('consume-credit');
    });
    Route::prefix('contracts')->name('contracts.')->group(function () {
        Route::get('/', [MembershipPlanController::class, 'index'])->name('index');
        Route::get('/create', [MembershipPlanController::class, 'create'])->name('create');
        Route::post('/', [MembershipPlanController::class, 'store'])->name('store');
        Route::get('/{membershipPlan}', [MembershipPlanController::class, 'show'])->name('show');
        Route::get('/{membershipPlan}/edit', [MembershipPlanController::class, 'edit'])->name('edit');
        Route::put('/{membershipPlan}', [MembershipPlanController::class, 'update'])->name('update');
        Route::delete('/{membershipPlan}', [MembershipPlanController::class, 'destroy'])->name('destroy');
        Route::get('/{membershipPlan}/check-deletion', [MembershipPlanController::class, 'checkDeletion'])->name('check-deletion');
    });
    Route::get('/finances', [FinancesController::class, 'index'])->name('finances.index');
    Route::post('/finances/export', [FinancesController::class, 'export'])->name('finances.export');
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::patch('/{payment}/mark-paid', [PaymentController::class, 'markAsPaid'])->name('mark-paid');
        Route::patch('/{payment}/mark-failed', [PaymentController::class, 'markAsFailed'])->name('mark-failed');
        Route::delete('/{payment}/cancel', [PaymentController::class, 'cancel'])->name('cancel');
        Route::post('/{payment}/refund', [PaymentController::class, 'refund'])->name('refund');
    });
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // Access Control / Scanner Management
    Route::prefix('access-control')->name('access-control.')->group(function () {
        Route::get('/', [AccessControlController::class, 'index'])->name('index');
        Route::get('/logs', [AccessControlController::class, 'logs'])->name('logs');
        Route::get('/statistics', [AccessControlController::class, 'statistics'])->name('statistics');

        // Scanner CRUD
        Route::post('/scanners', [AccessControlController::class, 'storeScanner'])->name('scanners.store');
        Route::put('/scanners/{scanner}', [AccessControlController::class, 'updateScanner'])->name('scanners.update');
        Route::delete('/scanners/{scanner}', [AccessControlController::class, 'destroyScanner'])->name('scanners.destroy');
        Route::post('/scanners/{scanner}/toggle', [AccessControlController::class, 'toggleScanner'])->name('scanners.toggle');
        Route::post('/scanners/{scanner}/regenerate-token', [AccessControlController::class, 'regenerateToken'])->name('scanners.regenerate-token');
        Route::get('/scanners/{scanner}/download-config', [AccessControlController::class, 'downloadConfig'])->name('scanners.download-config');

        // Gym Secret Key
        Route::post('/regenerate-secret-key', [AccessControlController::class, 'regenerateSecretKey'])->name('regenerate-secret-key');

        // Rolling QR-Code Settings
        Route::put('/rolling-qr-settings', [AccessControlController::class, 'updateRollingQrSettings'])->name('rolling-qr-settings.update');
    });

    // Data Transfer (Import/Export)
    Route::prefix('data-transfer')->name('data-transfer.')->group(function () {
        Route::get('/', [DataTransferController::class, 'index'])->name('index');
        Route::get('/export', [DataTransferController::class, 'export'])->name('export');
        Route::post('/validate', [DataTransferController::class, 'validateImport'])->name('validate');
        Route::post('/import', [DataTransferController::class, 'import'])->name('import');
        Route::post('/validate-csv', [DataTransferController::class, 'validateCsvImport'])->name('validate-csv');
        Route::post('/import-csv', [DataTransferController::class, 'importCsv'])->name('import-csv');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::put('/gym/{gym}', [SettingController::class, 'updateGym'])->name('gym.update');
        Route::post('/gym/logo/upload', [SettingController::class, 'uploadLogo'])->name('gym.logo.upload');
        Route::delete('/gym/logo/delete', [SettingController::class, 'deleteLogo'])->name('gym.logo.delete');
        Route::post('/gym-users', [SettingController::class, 'storeGymUser'])->name('gym-users.store');
        Route::put('/gym-users/{gymUser}', [SettingController::class, 'updateGymUser'])->name('gym-users.update');
        Route::delete('/gym-users/{gymUser}', [SettingController::class, 'destroyGymUser'])->name('gym-users.destroy');
        // Legal URLs
        Route::get('/legal-urls', [SettingController::class, 'getLegalUrls'])->name('legal-urls.index');
        Route::post('/legal-urls', [SettingController::class, 'storeLegalUrl'])->name('legal-urls.store');
        Route::delete('/legal-urls/{legalUrl}', [SettingController::class, 'destroyLegalUrl'])->name('legal-urls.destroy');
        // PWA Settings
        Route::put('/pwa/{gym}', [SettingController::class, 'updatePwaSettings'])->name('pwa.update');
        // Contract Settings
        Route::put('/contract-settings', [SettingController::class, 'updateContractSettings'])->name('contract-settings.update');
        Route::post('/contract-template/preview', [SettingController::class, 'previewContractTemplate'])->name('contract-template.preview');
        Route::prefix('payment-methods')->name('payment-methods.')->group(function () {
            Route::get('/', [PaymentMethodsController::class, 'index'])->name('index');
            Route::get('/overview', [PaymentMethodsController::class, 'overview'])->name('overview');
            Route::put('/update', [PaymentMethodsController::class, 'update'])->name('update');
        });
        Route::prefix('mollie')->name('mollie.')->group(function () {
            Route::get('/status', [PaymentMethodsController::class, 'mollieStatus'])->name('status');
            Route::delete('/remove', [PaymentMethodsController::class, 'removeMollieConfig'])->name('remove');
        });
        // Email Templates Routes
        Route::prefix('email-templates')->name('email-templates.')->group(function () {
            // Basic CRUD operations
            Route::get('/', [EmailTemplateController::class, 'index'])->name('index');
            Route::get('/{emailTemplate}', [EmailTemplateController::class, 'show'])->name('show');
            Route::post('/', [EmailTemplateController::class, 'store'])->name('store');
            Route::put('/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('update');
            Route::delete('/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->name('destroy');

            // Additional operations
            Route::post('/{emailTemplate}/duplicate', [EmailTemplateController::class, 'duplicate'])->name('duplicate');
            Route::get('/{emailTemplate}/preview', [EmailTemplateController::class, 'preview'])->name('preview');
            Route::post('/{emailTemplate}/render', [EmailTemplateController::class, 'render'])->name('render');

            // Attachments
            Route::post('/{emailTemplate}/attachments', [EmailTemplateController::class, 'uploadAttachment'])->name('upload-attachment');
            Route::delete('/{emailTemplate}/attachments/{attachment}', [EmailTemplateController::class, 'deleteAttachment'])->name('delete-attachment');

            // Bulk operations
            Route::post('/bulk-update', [EmailTemplateController::class, 'bulkUpdate'])->name('bulk-update');

            // Utility routes
            Route::get('/placeholders/all', [EmailTemplateController::class, 'placeholders'])->name('placeholders');
            Route::get('/type/{type}', [EmailTemplateController::class, 'byType'])->name('by-type');
            Route::get('/type/{type}/default', [EmailTemplateController::class, 'getDefault'])->name('get-default');
        });
    });
    Route::post('/gyms', [GymController::class, 'store'])->name('gyms.store');
    Route::get('/gyms/create', [GymController::class, 'create'])->name('gyms.create');
    Route::delete('/gyms/remove/{gym}', [GymController::class, 'remove'])->name('gyms.remove');
    Route::post('/user/switch-organization', [GymController::class, 'switchOrganization'])->name('user.switch-organization');

    // Profile routes
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('index');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });
});

// Benutzer-Simulation
Route::middleware(['auth:web', 'basic.auth'])->prefix('admin')->group(function () {
    Route::get('/impersonate', [ImpersonationController::class, 'index'])->name('impersonate.index');
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'impersonate'])->name('impersonate.start');
    Route::delete('/impersonate/stop', [ImpersonationController::class, 'stopImpersonating'])->name('impersonate.stop');
    Route::get('/impersonate/status', [ImpersonationController::class, 'status'])->name('impersonate.status');
});

// Zusätzliche Widget-Admin-Routes für AJAX-Calls
Route::prefix('admin/widget')->name('admin.widget.')->middleware('auth')->group(function() {
    Route::get('/contracts', function () {
        /** @var User $user */
        $user = Auth::user();

        $contracts = MembershipPlan::where('gym_id', $user->current_gym_id)
            ->where('is_active', true)
            ->get(['id', 'name', 'price', 'billing_cycle', 'is_active']);

        return response()->json(['contracts' => $contracts]);
    })->name('contracts');

    Route::put('/update', function(Request $request) {
        /** @var User $user */
        $user = Auth::user();
        $currentGym = $user?->currentGym;

        if (!$currentGym) {
            return response()->json([
                'success' => false,
                'message' => 'Kein Fitnessstudio ausgewählt.'
            ], 400);
        }

        $request->validate([
            'widget_enabled' => 'required|boolean',
            'widget_settings' => 'required|array',
            'widget_settings.colors' => 'required|array',
            'widget_settings.colors.primary' => 'required|string',
            'widget_settings.texts' => 'required|array',
            'widget_settings.texts.title' => 'required|string|max:255',
        ]);

        try {
            $currentGym->update([
                'widget_enabled' => $request->widget_enabled,
                'widget_settings' => $request->widget_settings,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Einstellungen erfolgreich gespeichert.',
                'gym' => [
                    'id' => $currentGym->id,
                    'name' => $currentGym->name,
                    'api_key' => $currentGym->api_key,
                    'widget_enabled' => $currentGym->widget_enabled,
                    'widget_settings' => $currentGym->widget_settings,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Speichern der Einstellungen: ' . $e->getMessage()
            ], 500);
        }
    })->name('update');

    Route::post('/regenerate-api-key', function() {
        /** @var User $user */
        $user = Auth::user();

        try {
            return response()->json([
                'success' => true,
                'api_key' => $user->currentGym->regenerateApiKey(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Generieren des API-Keys: ' . $e->getMessage()
            ], 500);
        }
    })->name('regenerate-api-key');

    Route::get('/api-keys', function() {
        /** @var User $user */
        $user = Auth::user();

        try {
            return response()->json([
                'success' => true,
                'public_key' => $user->currentGym->api_key,
                'private_key' => 'sk_live_notimplementedyet',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Laden der API-Keys: ' . $e->getMessage()
            ], 500);
        }
    })->name('api-keys');
});

Route::prefix('embed')->name('embed.')->group(function () {
    Route::get('gymportal-widget.css', function () {
        $response = response()->file(public_path('css/widget.css'));
        $response->headers->set('Content-Type', 'text/css');
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        return $response;
    })->name('widget.css')->withoutMiddleware(['web']);

    Route::get('widget.js', function () {
        $response = response()->file(public_path('js/widget.js'));
        $response->headers->set('Content-Type', 'application/javascript');
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        return $response;
    })->name('widget.js')->withoutMiddleware(['web']);
});

if (App::environment('local', 'development', 'staging')) {
    Route::get('/debug/widget-assets', function () {
        return [
            'js_file_exists' => file_exists(public_path('js/widget.js')),
            'css_file_exists' => file_exists(public_path('css/widget.css')),
            'js_path' => public_path('js/widget.js'),
            'css_path' => public_path('css/widget.css'),
            'js_readable' => is_readable(public_path('js/widget.js')),
            'css_readable' => is_readable(public_path('css/widget.css')),
            'js_size' => file_exists(public_path('js/widget.js')) ? filesize(public_path('js/widget.js')) : 0,
            'css_size' => file_exists(public_path('css/widget.css')) ? filesize(public_path('css/widget.css')) : 0,
            'public_path' => public_path(),
            'laravel_version' => app()->version(),
            'available_routes' => [
                'embed_widget_js' => route('embed.widget.js'),
                'embed_widget_css' => route('embed.widget.css'),
            ]
        ];
    });
}

Route::get('/widget-test', function () {
    return view('widget-test');
});
