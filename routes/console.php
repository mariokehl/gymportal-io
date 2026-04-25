<?php

use App\Models\MemberBlocklist;
use App\Services\SchedulerHealthCheckService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// ===================================
// MEMBERSHIP PROCESSING
// ===================================

// Main processing - runs daily at 2 AM
Schedule::command('memberships:daily-process', ['--verbose-log'])
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
    ->emailOutputTo(config('scheduler.notifications.admin_email', 'webmaster@gymportal.io'));

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

// Update Laravel Disposable Email
Schedule::command('disposable:update')->daily();

// Prune expired personal access tokens - runs daily
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// ===================================
// FRAUD PREVENTION / BLOCKLIST CLEANUP
// ===================================

// Abgelaufene Sperren aufräumen (DSGVO) + temporär gesperrte Mitglieder reaktivieren
Schedule::call(function () {
    $expireDays = (int) config('fraud.blocklist_expire_days', 1095);

    // Abgelaufene Einträge löschen (DSGVO: nach konfigurierten Tagen)
    $deleted = MemberBlocklist::where('blocked_until', '<', now()->subDays(30))->delete();

    // Alte Einträge nach Ablaufzeit löschen
    $purged = MemberBlocklist::where('created_at', '<', now()->subDays($expireDays))->delete();

    // Mitglieder reaktivieren, deren temporäre Sperre abgelaufen ist
    MemberBlocklist::where('blocked_until', '<', now())
        ->where('blocked_until', '>', now()->subDay()) // Nur kürzlich abgelaufen
        ->whereNotNull('original_member_id')
        ->with('member')
        ->each(function ($entry) {
            $entry->member?->update(['status' => 'active']);
        });

    if ($deleted || $purged) {
        Log::info("Fraud cleanup: {$deleted} abgelaufene + {$purged} alte Sperren entfernt");
    }
})->daily()
  ->at('04:00')
  ->timezone('Europe/Berlin')
  ->name('fraud.cleanup')
  ->withoutOverlapping();
