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
            $stuckPayments = Payment::where('status', 'unknown')
                ->where('updated_at', '<', now()->subHours(24))
                ->count();

            if ($stuckPayments > 0) {
                Log::warning("Found {$stuckPayments} stuck payments in processing state");
                $this->notifyAdministrators("Found {$stuckPayments} stuck payments");
            }

            // Check for overdue payments. The execution_date overrides the
            // due_date when set, so a payment is only overdue once that later
            // date has passed. Payment links are excluded because they are
            // settled by the member and never charged by the scheduler.
            $overdueCutoff = now()->subDays(7);
            $overduePayments = Payment::where('status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('payment_method')
                        ->orWhere('payment_method', '!=', 'mollie_paymentlink');
                })
                ->where(function ($q) use ($overdueCutoff) {
                    $q->where(function ($subQ) use ($overdueCutoff) {
                        $subQ->whereNull('execution_date')
                            ->where('due_date', '<', $overdueCutoff);
                    })->orWhere('execution_date', '<', $overdueCutoff);
                })
                ->count();

            // Threshold is 1% of all paid payments, but at least 10
            $overdueThreshold = max(10, Payment::where('status', 'paid')->count() * 0.01);

            if ($overduePayments > $overdueThreshold) {
                Log::warning("High number of overdue payments: {$overduePayments}");
                $this->notifyAdministrators("High number of overdue payments: {$overduePayments}");
            }

            // Check for memberships without payment methods. Free trial
            // memberships are exempt because no fee is ever charged for them.
            $membershipsWithoutPayment = Membership::where('status', 'active')
                ->whereDoesntHave('membershipPlan', function ($q) {
                    $q->where('is_free_trial_plan', true);
                })
                ->whereDoesntHave('member.paymentMethods', function ($q) {
                    $q->where('status', 'active');
                })
                ->count();

            if ($membershipsWithoutPayment > 0) {
                Log::warning("Found {$membershipsWithoutPayment} active memberships without payment methods");
            }

        } catch (Exception $e) {
            Log::error('Health check failed: '.$e->getMessage());
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
                    ->subject('[gymportal.io] Scheduler Alert: '.$message);
            });
        } catch (Exception $e) {
            Log::error('Failed to send admin notification: '.$e->getMessage());
        }
    }
}
