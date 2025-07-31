<?php

use App\Http\Controllers\Api\WidgetController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FinancesController;
use App\Http\Controllers\Web\GymController;
use App\Http\Controllers\Web\MemberController;
use App\Http\Controllers\Web\MembershipPlanController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PaymentController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\Settings\PaymentMethodsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

/*
| Web Routes
*/

// Public routes
Route::get('/', function () {
    return redirect('/login');
});

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

// Billing-Routen
Route::middleware(['auth', 'verified'])->group(function () {
    // Billing Management
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/subscribe', [BillingController::class, 'subscribeToProfessional'])->name('billing.subscribe');
    Route::post('/billing/cancel', [BillingController::class, 'cancelSubscription'])->name('billing.cancel');
});

// Paddle Webhook (ohne Auth-Middleware, aber mit IP-Adresse)
Route::post('/billing/webhook/paddle', [BillingController::class, 'paddleWebhook'])->name('billing.webhook')->middleware('paddleIp');

// Protected routes
Route::middleware(['auth:web', 'subscription'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('members', MemberController::class);
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
    Route::patch('/payments/{payment}/mark-paid', [PaymentController::class, 'markAsPaid'])->name('payments.mark-paid');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::put('/gym/{gym}', [SettingController::class, 'updateGym'])->name('gym.update');
        Route::post('/gym/logo/upload', [SettingController::class, 'uploadLogo'])->name('gym.logo.upload');
        Route::delete('/gym/logo/delete', [SettingController::class, 'deleteLogo'])->name('gym.logo.delete');
        Route::post('/gym-users', [SettingController::class, 'storeGymUser'])->name('gym-users.store');
        Route::put('/gym-users/{gymUser}', [SettingController::class, 'updateGymUser'])->name('gym-users.update');
        Route::delete('/gym-users/{gymUser}', [SettingController::class, 'destroyGymUser'])->name('gym-users.destroy');
        Route::prefix('payment-methods')->name('payment-methods.')->group(function () {
            Route::get('/', [PaymentMethodsController::class, 'index'])->name('index');
            Route::get('/overview', [PaymentMethodsController::class, 'overview'])->name('overview');
            Route::put('/update', [PaymentMethodsController::class, 'update'])->name('update');
        });
        Route::prefix('mollie')->name('.mollie.')->group(function () {
            Route::get('/status', [PaymentMethodsController::class, 'mollieStatus'])->name('status');
            Route::delete('/remove', [PaymentMethodsController::class, 'removeMollieConfig'])->name('remove');
        });
    });
    Route::post('/gyms', [GymController::class, 'store'])->name('gyms.store');
    Route::get('/gyms/create', [GymController::class, 'create'])->name('gyms.create');
    Route::delete('/gyms/remove/{gym}', [GymController::class, 'remove'])->name('gyms.remove');
    Route::post('/user/switch-organization', [GymController::class, 'switchOrganization'])->name('user.switch-organization');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Zusätzliche Widget-Admin-Routes für AJAX-Calls
Route::prefix('admin/widget')->name('admin.widget.')->middleware('auth')->group(function() {
    Route::put('/update', function(Request $request) {
        $user = auth()->user();
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
        try {
            return response()->json([
                'success' => true,
                'api_key' => auth()->user()->currentGym->regenerateApiKey(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Generieren des API-Keys: ' . $e->getMessage()
            ], 500);
        }
    })->name('regenerate-api-key');

    Route::get('/api-keys', function() {
        try {
            return response()->json([
                'success' => true,
                'public_key' => auth()->user()->currentGym->api_key,
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
    })->name('widget.css');

    Route::get('widget.js', function () {
        $response = response()->file(public_path('js/widget.js'));
        $response->headers->set('Content-Type', 'application/javascript');
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        return $response;
    })->name('widget.js');
});

Route::get('/debug/widget-assets', function () {
    $isProduction = App::environment('production');
    $isDebug = config('app.debug');

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
        'laravel_version' => (!$isProduction || $isDebug) ? app()->version() : 'hidden',
        'available_routes' => [
            'embed_widget_js' => route('embed.widget.js'),
            'embed_widget_css' => route('embed.widget.css'),
        ]
    ];
});

Route::get('/widget-test', function () {
    return view('widget-test');
});
