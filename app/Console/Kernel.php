<?php

namespace App\Console;

use App\Models\Membership;
use App\Models\Payment;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ===================================
        // MEMBERSHIP PROCESSING
        // ===================================

        // Main processing - runs daily at 2 AM
        $schedule->command('memberships:daily-process')
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
                $this->notifyAdministrators('Membership processing failed');
            })
            ->appendOutputTo(storage_path('logs/scheduler-memberships.log'))
            ->emailOutputTo('support@gymportal.io');

        // ===================================
        // MEMBERSHIP PAYMENT PROCESSING
        // ===================================

        // Retry failed payments - runs at 10 AM
        //$schedule->command('memberships:retry-failed-payments')
        //    ->dailyAt('10:00')
        //    ->timezone('Europe/Berlin')
        //    ->withoutOverlapping()
        //    ->onOneServer()
        //    ->runInBackground()
        //    ->appendOutputTo(storage_path('logs/scheduler-retry.log'));

        // Check expiring contracts - runs weekly on Mondays
        //$schedule->command('memberships:check-expiring')
        //    ->weeklyOn(1, '09:00')
        //    ->timezone('Europe/Berlin')
        //    ->withoutOverlapping()
        //    ->onOneServer()
        //    ->appendOutputTo(storage_path('logs/scheduler-expiring.log'));

        // Clean up old test data - runs monthly
        //$schedule->command('memberships:cleanup-test-data')
        //    ->monthlyOn(1, '03:00')
        //    ->timezone('Europe/Berlin')
        //    ->withoutOverlapping()
        //    ->onOneServer()
        //    ->appendOutputTo(storage_path('logs/scheduler-cleanup.log'));

        // ===================================
        // SEPA MANDATE PROCESSING
        // ===================================

        // Process pending SEPA mandates
        //$schedule->command('sepa:process-mandates')
        //    ->dailyAt('03:00')
        //    ->timezone('Europe/Berlin')
        //    ->withoutOverlapping()
        //    ->onOneServer()
        //    ->runInBackground()
        //    ->appendOutputTo(storage_path('logs/scheduler-sepa.log'));

        // ===================================
        // MONITORING & HEALTH CHECKS
        // ===================================

        // Health check - runs every hour
        $schedule->call(function () {
            $this->performHealthCheck();
        })->hourly()
          ->name('health-check')
          ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Perform health check for payment processing
     */
    protected function performHealthCheck(): void
    {
        try {
            // Check for stuck payments
            $stuckPayments = Payment::where('status', 'processing')
                ->where('updated_at', '<', now()->subHours(24))
                ->count();

            if ($stuckPayments > 0) {
                Log::warning("Found {$stuckPayments} stuck payments in processing state");
                $this->notifyAdministrators("Found {$stuckPayments} stuck payments");
            }

            // Check for overdue payments
            $overduePayments = Payment::where('status', 'pending')
                ->where('due_date', '<', now()->subDays(7))
                ->count();

            if ($overduePayments > 10) {
                Log::warning("High number of overdue payments: {$overduePayments}");
                $this->notifyAdministrators("High number of overdue payments: {$overduePayments}");
            }

            // Check for memberships without payment methods
            $membershipsWithoutPayment = Membership::where('status', 'active')
                ->whereDoesntHave('member.paymentMethods', function($q) {
                    $q->where('status', 'active');
                })
                ->count();

            if ($membershipsWithoutPayment > 0) {
                Log::warning("Found {$membershipsWithoutPayment} active memberships without payment methods");
            }

        } catch (Exception $e) {
            Log::error('Health check failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify administrators about critical issues
     */
    protected function notifyAdministrators(string $message): void
    {
        // Implement your notification logic here
        // This could be email, Slack, SMS, etc.

        try {
            // Example: Send to admin email
            Mail::raw($message, function ($mail) use ($message) {
                $mail->to(config('app.admin_email'))
                     ->subject('[gymportal.io] Scheduler Alert: ' . $message);
            });
        } catch (Exception $e) {
            Log::error('Failed to send admin notification: ' . $e->getMessage());
        }
    }
}
