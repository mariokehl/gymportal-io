<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use App\Services\MollieService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ProcessMembershipPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:process-payments
                            {--test : Run in test mode without making actual changes}
                            {--rollback : Rollback the last test run}
                            {--days=14 : Number of days to look ahead for payments}
                            {--gym-id= : Process only specific gym}
                            {--member-id= : Process only specific member}
                            {--verbose-log : Enable detailed logging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process membership payments and contract renewals';

    protected PaymentService $paymentService;
    protected MollieService $mollieService;

    /**
     * Track changes for rollback functionality
     */
    protected array $rollbackData = [];
    protected bool $testMode = false;
    protected bool $verboseLog = false;
    protected int $daysAhead = 14;

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
        $this->testMode = $this->option('test');
        $this->verboseLog = $this->option('verbose-log');
        $this->daysAhead = (int) $this->option('days');

        if ($this->option('rollback')) {
            return $this->performRollback();
        }

        $startTime = now();

        $this->info("===========================================");
        $this->info("Starting Membership Payment Processing");
        $this->info("Mode: " . ($this->testMode ? 'TEST' : 'PRODUCTION'));
        $this->info("Time: " . $startTime->format('Y-m-d H:i:s'));
        $this->info("Days ahead: " . $this->daysAhead);
        $this->info("===========================================\n");

        // Statistics tracking
        $stats = [
            'payments_created' => 0,
            'payments_processed' => 0,
            'payments_failed' => 0,
            'contracts_renewed' => 0,
            'contracts_expired' => 0,
            'errors' => [],
        ];

        try {
            // 1. Process due payments
            $this->info("Step 1: Processing due payments...");
            $paymentStats = $this->processDuePayments();
            $stats = array_merge($stats, $paymentStats);

            // 2. Create upcoming payments
            $this->info("\nStep 2: Creating upcoming payments...");
            $upcomingStats = $this->createUpcomingPayments();
            $stats['payments_created'] = $upcomingStats['created'];

            // 3. Process contract renewals
            $this->info("\nStep 3: Processing contract renewals...");
            $renewalStats = $this->processContractRenewals();
            $stats['contracts_renewed'] = $renewalStats['renewed'];
            $stats['contracts_expired'] = $renewalStats['expired'];

            // 4. Check and notify expiring contracts
            $this->info("\nStep 4: Checking expiring contracts...");
            $this->checkExpiringContracts();

        } catch (\Exception $e) {
            $this->error("Critical error during processing: " . $e->getMessage());
            Log::error('Membership payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $stats['errors'][] = $e->getMessage();
        }

        // Print summary
        $this->printSummary($stats, $startTime);

        // Save rollback data if in test mode
        if ($this->testMode && count($this->rollbackData) > 0) {
            $this->saveRollbackData();
        }

        return 0;
    }

    /**
     * Process payments that are due today or overdue
     */
    protected function processDuePayments(): array
    {
        $stats = [
            'payments_processed' => 0,
            'payments_failed' => 0,
            'errors' => [],
        ];

        $query = Payment::where('status', 'pending')
            ->where(function($q) {
                // Zahlung wird verarbeitet wenn:
                // - execution_date ist NULL und due_date ist heute oder in der Vergangenheit ODER
                // - execution_date ist gesetzt und heute oder in der Vergangenheit (überschreibt due_date)
                $q->where(function($subQ) {
                    $subQ->whereNull('execution_date')
                        ->where('due_date', '<=', Carbon::today());
                })->orWhere('execution_date', '<=', Carbon::today());
            });

        if ($gymId = $this->option('gym-id')) {
            $query->where('gym_id', $gymId);
        }

        if ($memberId = $this->option('member-id')) {
            $query->where('member_id', $memberId);
        }

        $duePayments = $query->with(['member', 'membership', 'member.defaultPaymentMethod'])
            ->get();

        $this->info("Found {$duePayments->count()} due payments to process");

        foreach ($duePayments as $payment) {
            try {
                $this->processPayment($payment);
                $stats['payments_processed']++;

                if ($this->verboseLog) {
                    $executionInfo = $payment->execution_date
                        ? " (execution_date: {$payment->execution_date->format('Y-m-d')})"
                        : " (no execution_date)";
                    $this->info("✓ Processed payment #{$payment->id} for member {$payment->member->full_name}{$executionInfo}");
                }
            } catch (\Exception $e) {
                $stats['payments_failed']++;
                $stats['errors'][] = "Payment #{$payment->id}: " . $e->getMessage();
                $this->error("✗ Failed to process payment #{$payment->id}: " . $e->getMessage());

                Log::error('Payment processing failed', [
                    'payment_id' => $payment->id,
                    'member_id' => $payment->member_id,
                    'due_date' => $payment->due_date,
                    'execution_date' => $payment->execution_date,
                    'error' => $e->getMessage()
                ]);

                // Continue with next payment (fehlertoleranz)
                continue;
            }
        }

        return $stats;
    }

    /**
     * Process a single payment
     */
    protected function processPayment(Payment $payment): void
    {
        $member = $payment->member;
        $paymentMethod = $member->defaultPaymentMethod;

        if (!$paymentMethod) {
            throw new \Exception("No default payment method for member #{$member->id}");
        }

        // In test mode, simulate the payment
        if ($this->testMode) {
            $this->logTestAction('payment_process', [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'member_id' => $member->id,
                'method' => $paymentMethod->type,
            ]);
            return;
        }

        DB::beginTransaction();
        try {
            switch ($paymentMethod->type) {
                case 'sepa_direct_debit':
                    $this->processSepaPayment($payment, $paymentMethod);
                    break;

                case 'mollie_creditcard':
                case 'mollie_directdebit':
                case 'mollie_paypal':
                    $this->processMolliePayment($payment, $paymentMethod);
                    break;

                case 'banktransfer':
                case 'invoice':
                    // These require manual processing, just send reminder
                    $this->sendPaymentReminder($payment);
                    break;

                case 'cash':
                    // Cash payments are handled in person
                    $this->logCashPaymentDue($payment);
                    break;

                default:
                    throw new \Exception("Unsupported payment method: {$paymentMethod->type}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process SEPA Direct Debit payment
     */
    protected function processSepaPayment(Payment $payment, PaymentMethod $paymentMethod): void
    {
        // Verify SEPA mandate is active
        if (!$paymentMethod->isSepaMandateValid()) {
            throw new \Exception("Invalid or inactive SEPA mandate");
        }

        // In production, this would trigger the actual SEPA collection
        // For now, we mark it as processing
        $payment->update([
            'status' => 'unknown',
            'notes' => $payment->notes . ' | SEPA collection initiated at ' . now()->toDateTimeString(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'sepa_collection_date' => now()->toDateString(),
                'sepa_mandate_reference' => $paymentMethod->sepa_mandate_reference,
            ])
        ]);

        Log::info('SEPA payment initiated', [
            'payment_id' => $payment->id,
            'mandate_reference' => $paymentMethod->sepa_mandate_reference,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * Process Mollie payment
     */
    protected function processMolliePayment(Payment $payment, PaymentMethod $paymentMethod): void
    {
        // Skip if the payment has already been created
        if ($payment->mollie_payment_id) {
            if ($this->verboseLog) {
                $this->info("→ Skipping payment creation at Mollie for {$payment->id} payment method #{$paymentMethod->id}");
            }
            return;
        }

        $member = $payment->member;

        // Create Mollie payment
        $molliePayment = $this->mollieService->createPaymentWithoutStoring(
            $member,
            $payment,
            $paymentMethod
        );

        Log::info('Mollie payment created', [
            'payment_id' => $payment->id,
            'mollie_id' => $molliePayment->id,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * Create upcoming payments for the next billing cycle
     */
    protected function createUpcomingPayments(): array
    {
        $stats = ['created' => 0, 'skipped' => 0];

        // Get active memberships that need upcoming payments
        $query = Membership::where('status', 'active')
            ->whereDate('start_date', '<=', Carbon::today());

        if ($gymId = $this->option('gym-id')) {
            $query->whereHas('member', function($q) use ($gymId) {
                $q->where('gym_id', $gymId);
            });
        }

        if ($memberId = $this->option('member-id')) {
            $query->where('member_id', $memberId);
        }

        $memberships = $query->with(['member', 'membershipPlan', 'payments'])
            ->get();

        foreach ($memberships as $membership) {
            try {
                $created = $this->createPaymentsForMembership($membership);
                $stats['created'] += $created;

                if ($this->verboseLog && $created > 0) {
                    $this->info("✓ Created {$created} payments for membership #{$membership->id}");
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to create payments for membership #{$membership->id}: " . $e->getMessage());
                Log::error('Failed to create upcoming payments', [
                    'membership_id' => $membership->id,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        return $stats;
    }

    /**
     * Create upcoming payments - mit Status-Check
     */
    protected function createPaymentsForMembership(Membership $membership): int
    {
        // Keine Zahlungen für nicht-aktive Mitgliedschaften erstellen
        if (!in_array($membership->status, ['active'])) {
            if ($this->verboseLog) {
                $this->info("→ Skipping payment creation for {$membership->status} membership #{$membership->id}");
            }
            return 0;
        }

        // Keine Zahlungen für gekündigte Mitgliedschaften
        if ($membership->cancellation_date &&
            Carbon::parse($membership->cancellation_date)->isPast()) {
            if ($this->verboseLog) {
                $this->info("→ Skipping payment creation for cancelled membership #{$membership->id}");
            }
            return 0;
        }

        $plan = $membership->membershipPlan;
        $member = $membership->member;
        $created = 0;

        // Calculate next payment date - mit membership_plan_id Check
        $lastPayment = $membership->payments()
            ->where('metadata->membership_plan_id', $plan->id)
            ->orderBy('due_date', 'desc')
            ->first();

        if (!$lastPayment) {
            // No payments exist for this plan, start from membership start date
            $nextPaymentDate = $membership->start_date->copy();
        } else {
            // Calculate next payment based on billing cycle
            $nextPaymentDate = $this->calculateNextPaymentDate(
                Carbon::parse($lastPayment->due_date),
                $plan->billing_cycle
            );
        }

        // Create payments for the next period
        $endDate = Carbon::today()->addDays($this->daysAhead);

        while ($nextPaymentDate <= $endDate) {
            // Don't create payment if membership ends before this date
            if ($membership->end_date && $nextPaymentDate > $membership->end_date) {
                break;
            }

            // Check if payment already exists for this plan
            $exists = Payment::where('membership_id', $membership->id)
                ->where('metadata->membership_plan_id', $plan->id)
                ->whereDate('due_date', $nextPaymentDate)
                ->whereNotNull('metadata->billing_cycle')  // Prüft ob billing_cycle existiert und nicht null ist
                ->exists();

            if (!$exists) {
                if ($this->testMode) {
                    $this->logTestAction('payment_create', [
                        'membership_id' => $membership->id,
                        'member_id' => $member->id,
                        'amount' => $plan->price,
                        'due_date' => $nextPaymentDate->toDateString(),
                    ]);
                } else {
                    $payment = $this->paymentService->createNextRecurringPayment(
                        $member,
                        $membership,
                        $nextPaymentDate
                    );

                    $this->rollbackData['payments'][] = $payment->id;
                }
                $created++;
            }

            // Move to next payment date
            $nextPaymentDate = $this->calculateNextPaymentDate($nextPaymentDate, $plan->billing_cycle);
        }

        return $created;
    }

    /**
     * Process contract renewals and expirations
     * Angepasst um mit bereits aktualisierten Status zu arbeiten
     */
    protected function processContractRenewals(): array
    {
        $stats = ['renewed' => 0, 'expired' => 0];

        // Get memberships expiring within the next 30 days
        // Schließt bereits expired/cancelled aus
        $expiringMemberships = Membership::whereIn('status', ['active', 'paused'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', Carbon::today()->addDays(30))
            ->whereNull('cancellation_date') // Keine gekündigten
            ->with(['member', 'membershipPlan'])
            ->get();

        $this->info("Found {$expiringMemberships->count()} memberships to review for renewal");

        foreach ($expiringMemberships as $membership) {
            try {
                // Check if membership should be renewed
                if ($this->shouldRenewMembership($membership)) {
                    $this->renewMembership($membership);
                    $stats['renewed']++;

                    if ($this->verboseLog) {
                        $this->info("✓ Renewed membership #{$membership->id}");
                    }
                } else if ($membership->end_date->isToday() || $membership->end_date->isPast()) {
                    // Nur expiren wenn noch nicht expired
                    if ($membership->status !== 'expired') {
                        $this->expireMembership($membership);
                        $stats['expired']++;

                        if ($this->verboseLog) {
                            $this->info("✓ Expired membership #{$membership->id}");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to process membership #{$membership->id}: " . $e->getMessage());
                Log::error('Contract renewal processing failed', [
                    'membership_id' => $membership->id,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }

        return $stats;
    }

    /**
     * Check if membership should be renewed
     */
    protected function shouldRenewMembership(Membership $membership): bool
    {
        // Don't renew if already cancelled
        if ($membership->cancellation_date) {
            return false;
        }

        $plan = $membership->membershipPlan;

        // Check if we're within the renewal window
        $renewalDate = $membership->end_date->copy()->addDay();
        $cancellationDeadline = $membership->end_date->copy()
            ->subDays($plan->cancellation_period_days ?? 30);

        // Renew if:
        // 1. We're past the cancellation deadline
        // 2. The membership hasn't been cancelled
        // 3. The end date is approaching
        return Carbon::today() >= $cancellationDeadline &&
               !$membership->cancellation_date &&
               $membership->end_date->diffInDays(Carbon::today()) <= 30;
    }

    /**
     * Renew a membership
     */
    protected function renewMembership(Membership $membership): void
    {
        $plan = $membership->membershipPlan;
        $newEndDate = $membership->end_date->copy()->addMonths($plan->commitment_months ?: 12);

        if ($this->testMode) {
            $this->logTestAction('membership_renewal', [
                'membership_id' => $membership->id,
                'old_end_date' => $membership->end_date->toDateString(),
                'new_end_date' => $newEndDate->toDateString(),
            ]);
            return;
        }

        DB::beginTransaction();

        try {
            // Update membership end date
            $membership->update([
                'end_date' => $newEndDate,
                'metadata' => array_merge($membership->metadata ?? [], [
                    'auto_renewed_at' => now()->toDateTimeString(),
                    'renewal_count' => ($membership->metadata['renewal_count'] ?? 0) + 1,
                ])
            ]);

            // Create first payment for new period
            $this->createPaymentsForMembership($membership);

            // Log renewal
            Log::info('Membership auto-renewed', [
                'membership_id' => $membership->id,
                'member_id' => $membership->member_id,
                'new_end_date' => $newEndDate->toDateString(),
            ]);

            $this->rollbackData['renewals'][] = [
                'membership_id' => $membership->id,
                'old_end_date' => $membership->getOriginal('end_date'),
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Expire a membership (nur wenn noch nicht durch UpdateMembershipStatuses erfolgt)
     */
    protected function expireMembership(Membership $membership): void
    {
        // Prüfen ob bereits expired durch anderen Prozess
        if ($membership->status === 'expired') {
            if ($this->verboseLog) {
                $this->info("→ Membership #{$membership->id} already expired, skipping");
            }
            return;
        }

        if ($this->testMode) {
            $this->logTestAction('membership_expiration', [
                'membership_id' => $membership->id,
                'end_date' => $membership->end_date->toDateString(),
            ]);
            return;
        }

        $membership->update([
            'status' => 'expired',
            'metadata' => array_merge($membership->metadata ?? [], [
                'expired_at' => now()->toDateTimeString(),
                'expired_by' => 'payment_processor', // Kennzeichnung der Quelle
            ])
        ]);

        // Update member status if no other active memberships
        $member = $membership->member;
        if (!$member->memberships()->where('status', 'active')->exists()) {
            $member->update(['status' => 'inactive']);
        }

        Log::info('Membership expired by payment processor', [
            'membership_id' => $membership->id,
            'member_id' => $membership->member_id,
        ]);
    }

    /**
     * Check and notify about expiring contracts
     */
    protected function checkExpiringContracts(): void
    {
        // Get memberships expiring in 30, 14, and 7 days
        $notificationDays = [30, 14, 7];

        foreach ($notificationDays as $days) {
            $expiringDate = Carbon::today()->addDays($days);

            $memberships = Membership::where('status', 'active')
                ->whereDate('end_date', $expiringDate)
                ->whereNull('cancellation_date')
                ->with(['member', 'membershipPlan'])
                ->get();

            foreach ($memberships as $membership) {
                if ($this->verboseLog) {
                    $this->info("→ Membership #{$membership->id} expires in {$days} days");
                }

                // Here you would send notifications
                // This is just a placeholder for the notification logic
                Log::info("Contract expiring soon", [
                    'membership_id' => $membership->id,
                    'member_id' => $membership->member_id,
                    'days_until_expiry' => $days,
                ]);
            }
        }
    }

    /**
     * Calculate next payment date based on billing cycle
     */
    protected function calculateNextPaymentDate(Carbon $currentDate, string $billingCycle): Carbon
    {
        return match($billingCycle) {
            'monthly' => $currentDate->copy()->addMonth(),
            'quarterly' => $currentDate->copy()->addMonths(3),
            'yearly' => $currentDate->copy()->addYear(),
            default => $currentDate->copy()->addMonth(),
        };
    }

    /**
     * Send payment reminder
     */
    protected function sendPaymentReminder(Payment $payment): void
    {
        // Placeholder for email notification
        Log::info('Payment reminder needed', [
            'payment_id' => $payment->id,
            'member_id' => $payment->member_id,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * Log cash payment due
     */
    protected function logCashPaymentDue(Payment $payment): void
    {
        Log::info('Cash payment due', [
            'payment_id' => $payment->id,
            'member_id' => $payment->member_id,
            'amount' => $payment->amount,
        ]);
    }

    /**
     * Log test action for rollback
     */
    protected function logTestAction(string $action, array $data): void
    {
        $this->rollbackData['test_actions'][] = [
            'action' => $action,
            'data' => $data,
            'timestamp' => now()->toDateTimeString(),
        ];

        if ($this->verboseLog) {
            $this->info("[TEST] {$action}: " . json_encode($data));
        }
    }

    /**
     * Save rollback data for test mode
     */
    protected function saveRollbackData(): void
    {
        $filename = storage_path('app/scheduler_rollback_' . now()->format('Y-m-d_His') . '.json');
        file_put_contents($filename, json_encode($this->rollbackData, JSON_PRETTY_PRINT));

        $this->info("\nRollback data saved to: {$filename}");
    }

    /**
     * Perform rollback of last test run
     */
    protected function performRollback(): int
    {
        $this->warn("Performing rollback...");

        // Find latest rollback file
        $files = glob(storage_path('app/scheduler_rollback_*.json'));
        if (empty($files)) {
            $this->error("No rollback data found");
            return 1;
        }

        rsort($files);
        $latestFile = $files[0];

        $this->info("Using rollback file: {$latestFile}");

        $data = json_decode(file_get_contents($latestFile), true);

        DB::beginTransaction();
        try {
            // Rollback payments
            if (isset($data['payments'])) {
                Payment::whereIn('id', $data['payments'])->delete();
                $this->info("Rolled back " . count($data['payments']) . " payments");
            }

            // Rollback renewals
            if (isset($data['renewals'])) {
                foreach ($data['renewals'] as $renewal) {
                    Membership::where('id', $renewal['membership_id'])
                        ->update(['end_date' => $renewal['old_end_date']]);
                }
                $this->info("Rolled back " . count($data['renewals']) . " membership renewals");
            }

            DB::commit();

            // Archive rollback file
            rename($latestFile, $latestFile . '.processed');

            $this->info("Rollback completed successfully");
            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Rollback failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Print processing summary
     */
    protected function printSummary(array $stats, Carbon $startTime): void
    {
        $duration = $startTime->diffInSeconds(now());

        $this->info("\n===========================================");
        $this->info("Processing Summary");
        $this->info("===========================================");
        $this->info("Duration: {$duration} seconds");
        $this->info("Mode: " . ($this->testMode ? 'TEST' : 'PRODUCTION'));
        $this->info("");
        $this->info("Payments:");
        $this->info("  - Created: {$stats['payments_created']}");
        $this->info("  - Processed: {$stats['payments_processed']}");
        $this->info("  - Failed: {$stats['payments_failed']}");
        $this->info("");
        $this->info("Contracts:");
        $this->info("  - Renewed: {$stats['contracts_renewed']}");
        $this->info("  - Expired: {$stats['contracts_expired']}");

        if (!empty($stats['errors'])) {
            $this->error("\nErrors encountered:");
            foreach ($stats['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }

        $this->info("===========================================\n");
    }
}
