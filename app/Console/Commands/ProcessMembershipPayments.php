<?php

namespace App\Console\Commands;

use App\Models\Addon;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\CreditLedgerService;
use App\Services\MollieService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    protected CreditLedgerService $creditLedger;

    /**
     * Track changes for rollback functionality
     */
    protected array $rollbackData = [];

    protected bool $testMode = false;

    protected bool $verboseLog = false;

    protected int $daysAhead = 14;

    public function __construct(
        PaymentService $paymentService,
        MollieService $mollieService,
        CreditLedgerService $creditLedger,
    ) {
        parent::__construct();
        $this->paymentService = $paymentService;
        $this->mollieService = $mollieService;
        $this->creditLedger = $creditLedger;
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

        $this->info('===========================================');
        $this->info('Starting Membership Payment Processing');
        $this->info('Mode: '.($this->testMode ? 'TEST' : 'PRODUCTION'));
        $this->info('Time: '.$startTime->format('Y-m-d H:i:s'));
        $this->info('Days ahead: '.$this->daysAhead);
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
            $this->info('Step 1: Processing due payments...');
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
            $this->error('Critical error during processing: '.$e->getMessage());
            Log::error('Membership payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
            ->where(function ($q) {
                // Zahlung wird verarbeitet wenn:
                // - execution_date ist NULL und due_date ist heute oder in der Vergangenheit ODER
                // - execution_date ist gesetzt und heute oder in der Vergangenheit (überschreibt due_date)
                $q->where(function ($subQ) {
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
                        : ' (no execution_date)';
                    $this->info("✓ Processed payment #{$payment->id} for member {$payment->member->full_name}{$executionInfo}");
                }
            } catch (\Exception $e) {
                $stats['payments_failed']++;
                $stats['errors'][] = "Payment #{$payment->id}: ".$e->getMessage();
                $this->error("✗ Failed to process payment #{$payment->id}: ".$e->getMessage());

                Log::error('Payment processing failed', [
                    'payment_id' => $payment->id,
                    'member_id' => $payment->member_id,
                    'due_date' => $payment->due_date,
                    'execution_date' => $payment->execution_date,
                    'error' => $e->getMessage(),
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
        // A payment due inside a scheduled pause period must never be collected.
        if ($this->cancelPaymentDuringPause($payment)) {
            return;
        }

        $member = $payment->member;
        $paymentMethod = $member->defaultPaymentMethod;

        if (! $paymentMethod) {
            throw new \Exception("No default payment method for member #{$member->id}");
        }

        if ($paymentMethod->status !== 'active') {
            $this->warn("Skipping payment #{$payment->id}: payment method #{$paymentMethod->id} is not active (status: {$paymentMethod->status})");

            return;
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
            // Apply stored credit first. If it fully covers the payment, it is
            // already marked paid and no collection over the method is needed.
            $creditResult = $this->creditLedger->settlePayment($payment);

            if ($creditResult['fully_covered']) {
                if ($this->verboseLog) {
                    $this->info("→ Payment #{$payment->id} fully covered by credit");
                }

                if ($payment->membership_id) {
                    $membership = $payment->membership;
                    if ($membership && $membership->status !== 'active') {
                        if (! $membership->activateMembership()) {
                            $membership->update(['status' => 'active']);
                        }
                    }
                }

                DB::commit();

                return;
            }

            if ($creditResult['redeemed_cents'] > 0) {
                // Only the remaining amount is now collected over the method.
                $payment->refresh();
            }

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
     * Cancel a payment whose due date falls into the membership's pause period.
     *
     * While a membership is paused no service is provided, so the charge must
     * not be collected. The payment is closed as canceled and carries a note
     * naming the pause period, so the reason stays visible in the member file.
     *
     * @return bool True when the payment was handled and must not be collected.
     */
    protected function cancelPaymentDuringPause(Payment $payment): bool
    {
        $membership = $payment->membership;

        if (! $membership) {
            return false;
        }

        // The due date decides, not the execution date: the charge belongs to
        // the period it is due for, no matter when it would be collected.
        $dueDate = $payment->due_date;

        if (! $dueDate || ! $membership->isDateWithinPause(Carbon::parse($dueDate))) {
            return false;
        }

        $pauseStart = $membership->pause_start_date->format('d.m.Y');
        $pauseEnd = $membership->pause_end_date->format('d.m.Y');
        $note = "Keine Ausführung aufgrund der Pausenzeit von {$pauseStart} bis {$pauseEnd}.";

        if ($this->testMode) {
            $this->logTestAction('payment_cancel_paused', [
                'payment_id' => $payment->id,
                'membership_id' => $membership->id,
                'due_date' => Carbon::parse($dueDate)->toDateString(),
                'pause_start_date' => $membership->pause_start_date->toDateString(),
                'pause_end_date' => $membership->pause_end_date->toDateString(),
            ]);

            return true;
        }

        $payment->update([
            'status' => 'canceled',
            'notes' => trim(trim((string) $payment->notes).' | '.$note, ' |'),
            'metadata' => array_merge($payment->metadata ?? [], [
                'canceled_reason' => 'membership_paused',
                'pause_start_date' => $membership->pause_start_date->toDateString(),
                'pause_end_date' => $membership->pause_end_date->toDateString(),
            ]),
        ]);

        if ($this->verboseLog) {
            $this->info("→ Canceled payment #{$payment->id}: {$note}");
        }

        Log::info('Payment canceled due to membership pause', [
            'payment_id' => $payment->id,
            'membership_id' => $membership->id,
            'due_date' => Carbon::parse($dueDate)->toDateString(),
            'pause_start_date' => $membership->pause_start_date->toDateString(),
            'pause_end_date' => $membership->pause_end_date->toDateString(),
        ]);

        return true;
    }

    /**
     * Process SEPA Direct Debit payment
     */
    protected function processSepaPayment(Payment $payment, PaymentMethod $paymentMethod): void
    {
        // Verify SEPA mandate is active
        if (! $paymentMethod->isSepaMandateValid()) {
            throw new \Exception('Invalid or inactive SEPA mandate');
        }

        // In production, this would trigger the actual SEPA collection
        // For now, we mark it as processing
        $payment->update([
            'status' => 'unknown',
            'notes' => $payment->notes.' | SEPA collection initiated at '.now()->toDateTimeString(),
            'metadata' => array_merge($payment->metadata ?? [], [
                'sepa_collection_date' => now()->toDateString(),
                'sepa_mandate_reference' => $paymentMethod->sepa_mandate_reference,
            ]),
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
        // Exclude free trial memberships (no payments needed for those)
        $query = Membership::where('status', 'active')
            ->whereDate('start_date', '<=', Carbon::today())
            ->whereHas('membershipPlan', function ($q) {
                $q->where('is_free_trial_plan', false)
                    ->orWhereNull('is_free_trial_plan');
            });

        if ($gymId = $this->option('gym-id')) {
            $query->whereHas('member', function ($q) use ($gymId) {
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
                $this->error("✗ Failed to create payments for membership #{$membership->id}: ".$e->getMessage());
                Log::error('Failed to create upcoming payments', [
                    'membership_id' => $membership->id,
                    'error' => $e->getMessage(),
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
        if (! in_array($membership->status, ['active'])) {
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

        // Keine Zahlungen für gelöschte Mitglieder
        if (! $membership->member) {
            if ($this->verboseLog) {
                $this->info("→ Skipping payment creation for deleted member in membership #{$membership->id}");
            }

            return 0;
        }

        $plan = $membership->membershipPlan;

        // Keine Zahlungen für Gratis-Mitgliedschaften (z.B. Probetraining, Überbrückungszeitraum)
        if ($plan && $plan->is_free_trial_plan) {
            if ($this->verboseLog) {
                $this->info("→ Skipping payment creation for free trial membership #{$membership->id}");
            }

            return 0;
        }
        $member = $membership->member;
        $created = 0;

        // Calculate next payment date - mit membership_plan_id Check
        $lastPayment = $membership->payments()
            ->where('metadata->membership_plan_id', $plan->id)
            ->orderBy('due_date', 'desc')
            ->first();

        if (! $lastPayment) {
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

            if (! $exists) {
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

        $created += $this->createAddonPayments($membership);

        return $created;
    }

    /**
     * Create the recurring add-on charges for every billing period up to the
     * look-ahead window.
     *
     * This walks the periods independently of the membership fee above: an
     * add-on booked after the fee for the running period was already created
     * still has to be billed for that period, which a loop starting at the
     * plan's next open payment would skip.
     */
    protected function createAddonPayments(Membership $membership): int
    {
        $plan = $membership->membershipPlan;
        $created = 0;

        $hasRecurringAddons = $membership->addons()
            ->where('billing_type', Addon::BILLING_TYPE_RECURRING)
            ->wherePivot('mode', '!=', 'included')
            ->exists();

        if (! $hasRecurringAddons) {
            return 0;
        }

        $billingDate = $membership->start_date->copy();
        $endDate = Carbon::today()->addDays($this->daysAhead);

        while ($billingDate <= $endDate) {
            if ($membership->end_date && $billingDate > $membership->end_date) {
                break;
            }

            $created += $this->createAddonPaymentsForPeriod($membership, $billingDate);

            $billingDate = $this->calculateNextPaymentDate($billingDate, $plan->billing_cycle);
        }

        return $created;
    }

    /**
     * Create the recurring add-on charges due together with the membership fee
     * of the given billing period.
     *
     * Add-ons are billed in sync with the fee, so they use the same due date.
     * Included add-ons are part of the plan and never charged.
     */
    protected function createAddonPaymentsForPeriod(Membership $membership, Carbon $billingDate): int
    {
        $member = $membership->member;
        $created = 0;

        $addons = $membership->addons()
            ->where('billing_type', Addon::BILLING_TYPE_RECURRING)
            ->get();

        foreach ($addons as $addon) {
            $pivot = $addon->pivot;

            // Included add-ons come with the plan and are never billed.
            if ($pivot->mode === 'included') {
                continue;
            }

            // A cancelled add-on is only billed for periods that start on or
            // before the date the cancellation takes effect.
            if ($pivot->cancellation_effective_at
                && $billingDate->gt(Carbon::parse($pivot->cancellation_effective_at))) {
                continue;
            }

            // The trial grants the remainder of the booking month, so the first
            // period is free and paid billing starts on the next 1st.
            if ($addon->trial_rest_of_month
                && $billingDate->lte(Carbon::parse($pivot->created_at)->endOfMonth())) {
                continue;
            }

            // Never bill an add-on before it was booked.
            if ($billingDate->lt(Carbon::parse($pivot->created_at)->startOfDay())) {
                continue;
            }

            // Match any add-on charge for this period, not just scheduler-made
            // ones: the widget already charges the first period at signup with
            // payment_type 'addon', and that must not be billed twice.
            $exists = Payment::where('membership_id', $membership->id)
                ->where('metadata->addon_id', $addon->id)
                ->whereDate('due_date', $billingDate)
                ->exists();

            if ($exists) {
                continue;
            }

            if ($this->testMode) {
                $this->logTestAction('addon_payment_create', [
                    'membership_id' => $membership->id,
                    'member_id' => $member->id,
                    'addon_id' => $addon->id,
                    'amount' => $pivot->price,
                    'due_date' => $billingDate->toDateString(),
                ]);
            } else {
                $payment = $this->paymentService->createRecurringAddonPayment(
                    $member,
                    $membership,
                    $addon,
                    $billingDate
                );

                $this->rollbackData['payments'][] = $payment->id;
            }

            $created++;
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

        // Get memberships expiring within the next month
        // Schließt bereits expired/cancelled und Gratis-Mitgliedschaften aus
        $expiringMemberships = Membership::whereIn('status', ['active', 'paused'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', Carbon::today()->addMonth())
            ->whereNull('cancellation_date') // Keine gekündigten
            ->whereHas('membershipPlan', function ($q) {
                // Keine Gratis-Mitgliedschaften (diese laufen einfach aus)
                $q->where('is_free_trial_plan', false)
                    ->orWhereNull('is_free_trial_plan');
            })
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
                } elseif ($membership->end_date->lt(Carbon::today())) {
                    // Erst expired wenn der komplette End-Tag vergangen ist (end_date < today)
                    if ($membership->status !== 'expired') {
                        $this->expireMembership($membership);
                        $stats['expired']++;

                        if ($this->verboseLog) {
                            $this->info("✓ Expired membership #{$membership->id}");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to process membership #{$membership->id}: ".$e->getMessage());
                Log::error('Contract renewal processing failed', [
                    'membership_id' => $membership->id,
                    'error' => $e->getMessage(),
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

        // Keine automatische Verlängerung für Gratis-Mitgliedschaften
        if ($plan && $plan->is_free_trial_plan) {
            return false;
        }

        // Indefinite (already converted) memberships have no end_date
        if ($membership->end_date === null) {
            return false;
        }

        // Renew once the cancellation deadline has passed — i.e. from the last
        // possible cancellation date onwards. Once the customer can no longer
        // cancel the current period, the contract is bound to auto-renew, so we
        // trigger the renewal on that date or later.
        $cancellationDeadline = $membership->cancellation_deadline;
        if ($cancellationDeadline === null) {
            return false;
        }

        return Carbon::today()->gte(Carbon::parse($cancellationDeadline)->startOfDay());
    }

    /**
     * Renew a membership
     */
    protected function renewMembership(Membership $membership): void
    {
        $plan = $membership->membershipPlan;
        $autoRenewType = $plan->auto_renew_type ?? 'indefinite';

        // German Fair Consumer Contracts Act (from 01.03.2022):
        // Renewal is always indefinite or monthly, regardless of the initial term.
        // Extending by another full initial term is not permitted.
        if ($autoRenewType === 'indefinite') {
            // Indefinite: end_date is set to null
            $newEndDate = null;
        } else {
            // Monthly rollover: extend by one month
            $newEndDate = $membership->end_date->copy()
                ->addDay()
                ->addMonths(1)
                ->subDay();
        }

        if ($this->testMode) {
            $this->logTestAction('membership_renewal', [
                'membership_id' => $membership->id,
                'old_end_date' => $membership->end_date->toDateString(),
                'new_end_date' => $newEndDate?->toDateString() ?? 'unbefristet',
                'auto_renew_type' => $autoRenewType,
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
                    'auto_renew_type' => $autoRenewType,
                    'converted_to_indefinite' => $newEndDate === null,
                ]),
            ]);

            // Create first payment for new period
            $this->createPaymentsForMembership($membership);

            // Log renewal
            Log::info('Membership auto-renewed', [
                'membership_id' => $membership->id,
                'member_id' => $membership->member_id,
                'new_end_date' => $newEndDate?->toDateString() ?? 'indefinite',
                'auto_renew_type' => $autoRenewType,
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

        $membership->markAsExpired('payment_processor');
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
                Log::info('Contract expiring soon', [
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
        return match ($billingCycle) {
            'monthly' => $currentDate->copy()->addMonth(),
            'quarterly' => $currentDate->copy()->addMonths(3),
            'biannual' => $currentDate->copy()->addMonths(6),
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
            $this->info("[TEST] {$action}: ".json_encode($data));
        }
    }

    /**
     * Save rollback data for test mode
     */
    protected function saveRollbackData(): void
    {
        $filename = storage_path('app/scheduler_rollback_'.now()->format('Y-m-d_His').'.json');
        file_put_contents($filename, json_encode($this->rollbackData, JSON_PRETTY_PRINT));

        $this->info("\nRollback data saved to: {$filename}");
    }

    /**
     * Perform rollback of last test run
     */
    protected function performRollback(): int
    {
        $this->warn('Performing rollback...');

        // Find latest rollback file
        $files = glob(storage_path('app/scheduler_rollback_*.json'));
        if (empty($files)) {
            $this->error('No rollback data found');

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
                $this->info('Rolled back '.count($data['payments']).' payments');
            }

            // Rollback renewals
            if (isset($data['renewals'])) {
                foreach ($data['renewals'] as $renewal) {
                    Membership::where('id', $renewal['membership_id'])
                        ->update(['end_date' => $renewal['old_end_date']]);
                }
                $this->info('Rolled back '.count($data['renewals']).' membership renewals');
            }

            DB::commit();

            // Archive rollback file
            rename($latestFile, $latestFile.'.processed');

            $this->info('Rollback completed successfully');

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Rollback failed: '.$e->getMessage());

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
        $this->info('Processing Summary');
        $this->info('===========================================');
        $this->info("Duration: {$duration} seconds");
        $this->info('Mode: '.($this->testMode ? 'TEST' : 'PRODUCTION'));
        $this->info('');
        $this->info('Payments:');
        $this->info("  - Created: {$stats['payments_created']}");
        $this->info("  - Processed: {$stats['payments_processed']}");
        $this->info("  - Failed: {$stats['payments_failed']}");
        $this->info('');
        $this->info('Contracts:');
        $this->info("  - Renewed: {$stats['contracts_renewed']}");
        $this->info("  - Expired: {$stats['contracts_expired']}");

        if (! empty($stats['errors'])) {
            $this->error("\nErrors encountered:");
            foreach ($stats['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }

        $this->info("===========================================\n");
    }
}
