<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use App\Services\MollieService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryFailedPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:retry-failed-payments
                            {--max-retries=3 : Maximum retry attempts}
                            {--days-back=7 : How many days back to look for failed payments}
                            {--gym-id= : Process only specific gym}
                            {--dry-run : Run without making actual changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed membership payments';

    protected PaymentService $paymentService;
    protected MollieService $mollieService;

    public function __construct(PaymentService $paymentService, MollieService $mollieService)
    {
        parent::__construct();
        $this->paymentService = $paymentService;
        $this->mollieService = $mollieService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $maxRetries = (int) $this->option('max-retries');
        $daysBack = (int) $this->option('days-back');
        $dryRun = $this->option('dry-run');
        $gymId = $this->option('gym-id');

        $this->info("===========================================");
        $this->info("Retrying Failed Payments");
        $this->info("Mode: " . ($dryRun ? 'DRY RUN' : 'PRODUCTION'));
        $this->info("Max retries: {$maxRetries}");
        $this->info("Days back: {$daysBack}");
        $this->info("===========================================\n");

        $stats = [
            'total' => 0,
            'retried' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // Get failed payments
        $query = Payment::whereIn('status', ['failed', 'expired'])
            ->where('created_at', '>=', Carbon::now()->subDays($daysBack))
            ->whereRaw('COALESCE(JSON_EXTRACT(metadata, "$.retry_count"), 0) < ?', [$maxRetries]);

        if ($gymId) {
            $query->where('gym_id', $gymId);
        }

        $failedPayments = $query->with(['member', 'membership', 'member.defaultPaymentMethod'])
            ->get();

        $stats['total'] = $failedPayments->count();
        $this->info("Found {$stats['total']} failed payments to retry\n");

        foreach ($failedPayments as $payment) {
            try {
                if (!$this->shouldRetryPayment($payment)) {
                    $stats['skipped']++;
                    $this->line("⊘ Skipped payment #{$payment->id} - conditions not met");
                    continue;
                }

                if ($dryRun) {
                    $this->info("[DRY RUN] Would retry payment #{$payment->id}");
                    $stats['retried']++;
                    continue;
                }

                $result = $this->retryPayment($payment);
                $stats['retried']++;

                if ($result) {
                    $stats['successful']++;
                    $this->info("✓ Successfully retried payment #{$payment->id}");
                } else {
                    $stats['failed']++;
                    $this->error("✗ Failed to retry payment #{$payment->id}");
                }

            } catch (\Exception $e) {
                $stats['failed']++;
                $this->error("✗ Error retrying payment #{$payment->id}: " . $e->getMessage());

                Log::error('Payment retry failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->printSummary($stats);
        return 0;
    }

    /**
     * Check if payment should be retried
     */
    protected function shouldRetryPayment(Payment $payment): bool
    {
        // Don't retry if membership is cancelled or expired
        if ($payment->membership && in_array($payment->membership->status, ['cancelled', 'expired'])) {
            return false;
        }

        // Don't retry if member is inactive
        if ($payment->member && $payment->member->status === 'inactive') {
            return false;
        }

        // Don't retry if no valid payment method
        $paymentMethod = $payment->member->defaultPaymentMethod;
        if (!$paymentMethod || $paymentMethod->status !== 'active') {
            return false;
        }

        // Check payment method specific conditions
        if ($paymentMethod->type === 'sepa_direct_debit' && !$paymentMethod->isSepaMandateValid()) {
            return false;
        }

        // Don't retry if payment is too old (configurable)
        if ($payment->created_at < Carbon::now()->subDays(30)) {
            return false;
        }

        return true;
    }

    /**
     * Retry a failed payment
     */
    protected function retryPayment(Payment $payment): bool
    {
        DB::beginTransaction();
        try {
            $member = $payment->member;
            $paymentMethod = $member->defaultPaymentMethod;

            // Update retry count
            $retryCount = $payment->metadata['retry_count'] ?? 0;
            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'retry_count' => $retryCount + 1,
                    'last_retry_at' => now()->toDateTimeString(),
                    'retry_method' => 'scheduled',
                ])
            ]);

            // Process based on payment method
            switch ($paymentMethod->type) {
                case 'sepa_direct_debit':
                    $this->retrySepaPayment($payment, $paymentMethod);
                    break;

                case 'mollie_creditcard':
                case 'mollie_directdebit':
                case 'mollie_paypal':
                    $this->retryMolliePayment($payment, $paymentMethod);
                    break;

                default:
                    // For manual payment methods, just reset status
                    $payment->update([
                        'status' => 'pending',
                        'notes' => $payment->notes . ' | Retry attempt #' . ($retryCount + 1)
                    ]);
            }

            DB::commit();

            Log::info('Payment retry initiated', [
                'payment_id' => $payment->id,
                'retry_count' => $retryCount + 1,
                'method' => $paymentMethod->type,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Retry SEPA payment
     */
    protected function retrySepaPayment(Payment $payment, PaymentMethod $paymentMethod): void
    {
        $payment->update([
            'status' => 'unknown',
            'notes' => $payment->notes . ' | SEPA retry initiated at ' . now()->toDateTimeString(),
        ]);
    }

    /**
     * Retry Mollie payment
     */
    protected function retryMolliePayment(Payment $payment, PaymentMethod $paymentMethod): void
    {
        $member = $payment->member;

        // Create new Mollie payment
        $molliePayment = $this->mollieService->createPaymentWithoutStoring(
            $member,
            $payment,
            $paymentMethod
        );

        $payment->update([
            'status' => 'unknown',
            'mollie_payment_id' => $molliePayment->id,
            'notes' => $payment->notes . ' | Mollie retry initiated',
        ]);
    }

    /**
     * Print summary
     */
    protected function printSummary(array $stats): void
    {
        $this->info("\n===========================================");
        $this->info("Retry Summary");
        $this->info("===========================================");
        $this->info("Total failed payments: {$stats['total']}");
        $this->info("Payments retried: {$stats['retried']}");
        if (!$this->option('dry-run')) {
            $this->info("Successful retries: {$stats['successful']}");
            $this->info("Failed retries: {$stats['failed']}");
        }
        $this->info("Skipped: {$stats['skipped']}");
        $this->info("===========================================\n");
    }
}
