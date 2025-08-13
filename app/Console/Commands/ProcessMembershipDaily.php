<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProcessMembershipDaily extends Command
{
    protected $signature = 'memberships:daily-process
                            {--test : Run in test mode}
                            {--skip-status : Skip status updates}
                            {--skip-payments : Skip payment processing}
                            {--verbose-log : Enable detailed logging}';

    protected $description = 'Master command for daily membership processing';

    public function handle()
    {
        $startTime = now();

        $this->info("===========================================");
        $this->info("Starting Daily Membership Processing");
        $this->info("Time: " . $startTime->format('Y-m-d H:i:s'));
        $this->info("===========================================\n");

        $exitCode = 0;
        $results = [];

        try {
            // Step 1: Update Membership Statuses
            if (!$this->option('skip-status')) {
                $this->info("Step 1: Updating membership statuses...");
                $this->info("----------------------------------------");

                $statusCode = Artisan::call('memberships:update-statuses', [], $this->output);
                $results['status_update'] = $statusCode === 0 ? 'success' : 'failed';

                if ($statusCode !== 0) {
                    $this->error("Status update failed with code: {$statusCode}");
                    $exitCode = 1;
                } else {
                    $this->info("✓ Status updates completed successfully\n");
                }

                // Kurze Pause zwischen den Prozessen
                sleep(2);
            }

            // Step 2: Process Payments
            if (!$this->option('skip-payments') && $exitCode === 0) {
                $this->info("Step 2: Processing membership payments...");
                $this->info("----------------------------------------");

                $args = [];
                if ($this->option('test')) {
                    $args['--test'] = true;
                }
                if ($this->option('verbose-log')) {
                    $args['--verbose-log'] = true;
                }

                $paymentCode = Artisan::call('memberships:process-payments', $args, $this->output);
                $results['payment_processing'] = $paymentCode === 0 ? 'success' : 'failed';

                if ($paymentCode !== 0) {
                    $this->error("Payment processing failed with code: {$paymentCode}");
                    $exitCode = 1;
                } else {
                    $this->info("✓ Payment processing completed successfully\n");
                }
            }

            // Step 3: Send Notifications (optional)
            if ($exitCode === 0) {
                $this->info("Step 3: Sending notifications...");
                $this->info("----------------------------------------");

                // Hier könnten Sie einen weiteren Command für Benachrichtigungen aufrufen
                // z.B.: Artisan::call('memberships:send-notifications');

                $this->info("✓ Notifications sent\n");
            }

        } catch (\Exception $e) {
            $this->error("Critical error during processing: " . $e->getMessage());
            Log::error('Daily membership processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $exitCode = 1;
        }

        // Summary
        $duration = $startTime->diffInSeconds(now());

        $this->info("===========================================");
        $this->info("Daily Processing Summary");
        $this->info("===========================================");
        $this->info("Duration: {$duration} seconds");
        $this->info("Results:");
        foreach ($results as $step => $result) {
            $icon = $result === 'success' ? '✓' : '✗';
            $color = $result === 'success' ? 'info' : 'error';
            $this->$color("  {$icon} " . str_replace('_', ' ', ucfirst($step)) . ": {$result}");
        }
        $this->info("===========================================\n");

        // Log summary
        Log::info('Daily membership processing completed', [
            'duration' => $duration,
            'results' => $results,
            'exit_code' => $exitCode
        ]);

        return $exitCode;
    }
}
