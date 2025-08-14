<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SchedulerHealthCheckService
{
    /**
     * Perform health check for payment processing
     */
    public function performHealthCheck(): void
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
    public function notifyAdministrators(string $message): void
    {
        // Implement your notification logic here
        // This could be email, Slack, SMS, etc.

        try {
            // Example: Send to admin email
            Mail::raw($message, function ($mail) use ($message) {
                $mail->to(config('scheduler.notifications.admin_email'))
                     ->subject('[gymportal.io] Scheduler Alert: ' . $message);
            });
        } catch (Exception $e) {
            Log::error('Failed to send admin notification: ' . $e->getMessage());
        }
    }
}
