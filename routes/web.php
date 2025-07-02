<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FinanceController;
use App\Http\Controllers\Web\GymController;
use App\Http\Controllers\Web\MemberController;
use App\Http\Controllers\Web\MembershipPlanController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\SettingController;
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

// Protected routes
Route::middleware('auth:web')->group(function () {
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
    Route::get('/finances', [FinanceController::class, 'index'])->name('finances.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');
        Route::put('/gym/{gym}', [SettingController::class, 'updateGym'])->name('gym.update');
        Route::post('/gym/logo/upload', [SettingController::class, 'uploadLogo'])->name('gym.logo.upload');
        Route::delete('/gym/logo/delete', [SettingController::class, 'deleteLogo'])->name('gym.logo.delete');
        Route::post('/gym-users', [SettingController::class, 'storeGymUser'])->name('gym-users.store');
        Route::put('/gym-users/{gymUser}', [SettingController::class, 'updateGymUser'])->name('gym-users.update');
        Route::delete('/gym-users/{gymUser}', [SettingController::class, 'destroyGymUser'])->name('gym-users.destroy');
    });
    Route::post('/gyms', [GymController::class, 'store'])->name('gyms.store');
    Route::get('/gyms/create', [GymController::class, 'create'])->name('gyms.create');
    Route::delete('/gyms/remove/{gym}', [GymController::class, 'remove'])->name('gyms.remove');
    Route::post('/user/switch-organization', [GymController::class, 'switchOrganization'])->name('user.switch-organization');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
