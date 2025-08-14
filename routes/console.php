<?php

use App\Services\SchedulerHealthCheckService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// ===================================
// MEMBERSHIP PROCESSING
// ===================================

// Main processing - runs daily at 2 AM
Schedule::command('memberships:daily-process')
    ->dailyAt('02:00')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping()
    ->onOneServer()
    ->onSuccess(function () {
        Log::info('Daily membership processing completed successfully');
    })
    ->onFailure(function () {
        Log::error('Daily membership processing failed');
        // Send alert to administrators
        app(SchedulerHealthCheckService::class)->notifyAdministrators('Membership processing failed');
    })
    ->appendOutputTo(storage_path('logs/scheduler-memberships.log'))
    ->emailOutputTo('support@gymportal.io');

// ===================================
// MEMBERSHIP PAYMENT PROCESSING
// ===================================

// Retry failed payments - runs at 10 AM
// Schedule::command('memberships:retry-failed-payments')
//     ->dailyAt('10:00')
//     ->timezone('Europe/Berlin')
//     ->withoutOverlapping()
//     ->onOneServer()
//     ->runInBackground()
//     ->appendOutputTo(storage_path('logs/scheduler-retry.log'));

// Check expiring contracts - runs weekly on Mondays
// Schedule::command('memberships:check-expiring')
//     ->weeklyOn(1, '09:00')
//     ->timezone('Europe/Berlin')
//     ->withoutOverlapping()
//     ->onOneServer()
//     ->appendOutputTo(storage_path('logs/scheduler-expiring.log'));

// Clean up old test data - runs monthly
// Schedule::command('memberships:cleanup-test-data')
//     ->monthlyOn(1, '03:00')
//     ->timezone('Europe/Berlin')
//     ->withoutOverlapping()
//     ->onOneServer()
//     ->appendOutputTo(storage_path('logs/scheduler-cleanup.log'));

// ===================================
// SEPA MANDATE PROCESSING
// ===================================

// Process pending SEPA mandates
// Schedule::command('sepa:process-mandates')
//     ->dailyAt('03:00')
//     ->timezone('Europe/Berlin')
//     ->withoutOverlapping()
//     ->onOneServer()
//     ->runInBackground()
//     ->appendOutputTo(storage_path('logs/scheduler-sepa.log'));

// ===================================
// MONITORING & HEALTH CHECKS
// ===================================

// Health check - runs every hour
Schedule::call(function () {
    app(SchedulerHealthCheckService::class)->performHealthCheck();
})->hourly()
  ->name('health-check')
  ->withoutOverlapping();
