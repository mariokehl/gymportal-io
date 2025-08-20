<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Membership;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessSepaMandates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sepa:process-mandates
                            {--activate : Activate signed mandates}
                            {--remind : Send reminders for pending mandates}
                            {--expire : Process expired mandates}
                            {--validate : Validate IBAN and mandate data}
                            {--first-payment : Create first payment after mandate activation}
                            {--days=14 : Days to look back for pending mandates}
                            {--gym-id= : Process only specific gym}
                            {--dry-run : Preview without making changes}
                            {--verbose-log : Enable detailed logging}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process SEPA mandates (activation, reminders, validation)';

    protected PaymentService $paymentService;
    protected array $stats = [
        'total_processed' => 0,
        'pending_mandates' => 0,
        'activated_mandates' => 0,
        'expired_mandates' => 0,
        'reminders_sent' => 0,
        'validation_errors' => 0,
        'first_payments_created' => 0,
    ];

    public function __construct(PaymentService $paymentService)
    {
        parent::__construct();
        $this->paymentService = $paymentService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $verboseLog = $this->option('verbose-log');
        $daysBack = (int) $this->option('days');
        $gymId = $this->option('gym-id');

        $this->info("===========================================");
        $this->info("SEPA Mandate Processing");
        $this->info("Mode: " . ($dryRun ? 'DRY RUN' : 'PRODUCTION'));
        $this->info("Looking back: {$daysBack} days");
        $this->info("===========================================\n");

        try {
            // 1. Process pending mandates that need activation
            if ($this->option('activate')) {
                $this->info("Step 1: Activating signed mandates...");
                $this->activateSignedMandates($gymId, $dryRun, $verboseLog);
            }

            // 2. Send reminders for pending mandates
            if ($this->option('remind')) {
                $this->info("\nStep 2: Sending mandate reminders...");
                $this->sendMandateReminders($daysBack, $gymId, $dryRun, $verboseLog);
            }

            // 3. Process expired mandates
            if ($this->option('expire')) {
                $this->info("\nStep 3: Processing expired mandates...");
                $this->processExpiredMandates($gymId, $dryRun, $verboseLog);
            }

            // 4. Validate IBAN and mandate data
            if ($this->option('validate')) {
                $this->info("\nStep 4: Validating mandate data...");
                $this->validateMandates($gymId, $dryRun, $verboseLog);
            }

            // 5. Create first payment after mandate activation
            if ($this->option('first-payment')) {
                $this->info("\nStep 5: Creating first payments...");
                $this->createFirstPaymentsAfterActivation($gymId, $dryRun, $verboseLog);
            }

            // If no specific option selected, run all
            if (!$this->option('activate') && !$this->option('remind') &&
                !$this->option('expire') && !$this->option('validate') &&
                !$this->option('first-payment')) {
                $this->info("Running all SEPA processing tasks...\n");
                $this->activateSignedMandates($gymId, $dryRun, $verboseLog);
                $this->sendMandateReminders($daysBack, $gymId, $dryRun, $verboseLog);
                $this->processExpiredMandates($gymId, $dryRun, $verboseLog);
                $this->validateMandates($gymId, $dryRun, $verboseLog);
                $this->createFirstPaymentsAfterActivation($gymId, $dryRun, $verboseLog);
            }

        } catch (\Exception $e) {
            $this->error("Critical error during SEPA processing: " . $e->getMessage());
            Log::error('SEPA mandate processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $this->printSummary();
        return 0;
    }

    /**
     * Activate signed SEPA mandates
     */
    protected function activateSignedMandates(?int $gymId, bool $dryRun, bool $verboseLog): void
    {
        $query = PaymentMethod::sepa()
            ->where('sepa_mandate_status', 'signed')
            ->whereNotNull('sepa_mandate_signed_at')
            ->with(['member', 'member.gym']);

        if ($gymId) {
            $query->whereHas('member', function($q) use ($gymId) {
                $q->where('gym_id', $gymId);
            });
        }

        $signedMandates = $query->get();

        $this->line("Found {$signedMandates->count()} signed mandates to activate");

        foreach ($signedMandates as $mandate) {
            try {
                $this->stats['total_processed']++;

                if ($dryRun) {
                    $this->line("[DRY RUN] Would activate mandate for member #{$mandate->member_id}");
                    continue;
                }

                // Generate creditor ID if not exists
                $creditorId = $this->getCreditorId($mandate->member->gym);

                // Activate the mandate
                if ($mandate->activateSepaMandate($creditorId)) {
                    $this->stats['activated_mandates']++;

                    // Activate member and membership if pending
                    $this->activatePendingMemberAndMembership($mandate->member);

                    if ($verboseLog) {
                        $this->info("✓ Activated mandate for {$mandate->member->full_name} (#{$mandate->member_id})");
                    }

                    // Send confirmation email
                    $this->sendMandateActivationConfirmation($mandate);

                    Log::info('SEPA mandate activated', [
                        'payment_method_id' => $mandate->id,
                        'member_id' => $mandate->member_id,
                        'mandate_reference' => $mandate->sepa_mandate_reference,
                    ]);
                } else {
                    $this->warn("Failed to activate mandate for member #{$mandate->member_id}");
                }

            } catch (\Exception $e) {
                $this->error("Error activating mandate for member #{$mandate->member_id}: " . $e->getMessage());
                Log::error('SEPA mandate activation failed', [
                    'payment_method_id' => $mandate->id,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
    }

    /**
     * Send reminders for pending SEPA mandates
     */
    protected function sendMandateReminders(int $daysBack, ?int $gymId, bool $dryRun, bool $verboseLog): void
    {
        $cutoffDate = Carbon::now()->subDays($daysBack);

        $query = PaymentMethod::sepa()
            ->where('sepa_mandate_status', 'pending')
            ->where('created_at', '>=', $cutoffDate)
            ->with(['member']);

        if ($gymId) {
            $query->whereHas('member', function($q) use ($gymId) {
                $q->where('gym_id', $gymId);
            });
        }

        $pendingMandates = $query->get();
        $this->stats['pending_mandates'] = $pendingMandates->count();

        $this->line("Found {$pendingMandates->count()} pending mandates");

        foreach ($pendingMandates as $mandate) {
            try {
                $member = $mandate->member;
                $daysPending = $mandate->created_at->diffInDays(now());

                // Determine reminder type based on days pending
                $reminderType = $this->determineReminderType($daysPending);

                if (!$reminderType) {
                    continue; // No reminder needed yet
                }

                // Check if reminder was already sent recently
                if ($this->wasReminderRecentlySent($mandate, $reminderType)) {
                    if ($verboseLog) {
                        $this->line("⊘ Skipping reminder for member #{$member->id} - already sent");
                    }
                    continue;
                }

                if ($dryRun) {
                    $this->line("[DRY RUN] Would send {$reminderType} reminder to {$member->email}");
                    continue;
                }

                // Send reminder email
                $this->sendMandateReminder($mandate, $reminderType);
                $this->stats['reminders_sent']++;

                if ($verboseLog) {
                    $this->info("✓ Sent {$reminderType} reminder to {$member->full_name}");
                }

                // Update mandate metadata
                $this->updateReminderMetadata($mandate, $reminderType);

            } catch (\Exception $e) {
                $this->error("Error sending reminder for member #{$mandate->member_id}: " . $e->getMessage());
                Log::error('SEPA reminder failed', [
                    'payment_method_id' => $mandate->id,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
    }

    /**
     * Process expired SEPA mandates
     */
    protected function processExpiredMandates(?int $gymId, bool $dryRun, bool $verboseLog): void
    {
        // Mandates expire after 36 months without use (SEPA regulation)
        $expiryDate = Carbon::now()->subMonths(36);

        $query = PaymentMethod::sepa()
            ->where('sepa_mandate_status', 'active')
            ->where(function($q) use ($expiryDate) {
                $q->where('updated_at', '<', $expiryDate)
                  ->orWhereHas('member.payments', function($subQ) use ($expiryDate) {
                      $subQ->where('payment_method', 'sepa_direct_debit')
                           ->where('status', 'paid')
                           ->havingRaw('MAX(paid_date) < ?', [$expiryDate]);
                  }, '=', 0);
            });

        if ($gymId) {
            $query->whereHas('member', function($q) use ($gymId) {
                $q->where('gym_id', $gymId);
            });
        }

        $expiredMandates = $query->get();

        $this->line("Found {$expiredMandates->count()} expired mandates");

        foreach ($expiredMandates as $mandate) {
            try {
                if ($dryRun) {
                    $this->line("[DRY RUN] Would expire mandate for member #{$mandate->member_id}");
                    continue;
                }

                // Mark mandate as expired
                $mandate->update([
                    'sepa_mandate_status' => 'expired',
                    'status' => 'expired',
                    'sepa_mandate_data' => array_merge($mandate->sepa_mandate_data ?? [], [
                        'expired_at' => now()->toDateTimeString(),
                        'expiry_reason' => 'unused_for_36_months'
                    ])
                ]);

                $this->stats['expired_mandates']++;

                if ($verboseLog) {
                    $this->info("✓ Expired mandate for member #{$mandate->member_id}");
                }

                // Notify member about expiration
                $this->sendMandateExpirationNotice($mandate);

                // Check if member needs new payment method
                $this->checkAlternativePaymentMethod($mandate->member);

                Log::info('SEPA mandate expired', [
                    'payment_method_id' => $mandate->id,
                    'member_id' => $mandate->member_id,
                ]);

            } catch (\Exception $e) {
                $this->error("Error expiring mandate for member #{$mandate->member_id}: " . $e->getMessage());
                continue;
            }
        }
    }

    /**
     * Validate SEPA mandates
     */
    protected function validateMandates(?int $gymId, bool $dryRun, bool $verboseLog): void
    {
        $query = PaymentMethod::sepa()
            ->whereIn('sepa_mandate_status', ['pending', 'signed', 'active'])
            ->with(['member']);

        if ($gymId) {
            $query->whereHas('member', function($q) use ($gymId) {
                $q->where('gym_id', $gymId);
            });
        }

        $mandates = $query->get();

        $this->line("Validating {$mandates->count()} mandates");

        $validationErrors = [];

        foreach ($mandates as $mandate) {
            $errors = [];

            // Validate IBAN
            if (!$this->validateIBAN($mandate->iban)) {
                $errors[] = "Invalid IBAN";
            }

            // Validate mandate reference
            if (empty($mandate->sepa_mandate_reference)) {
                $errors[] = "Missing mandate reference";
            }

            // Validate member data
            $member = $mandate->member;
            if (empty($member->first_name) || empty($member->last_name)) {
                $errors[] = "Incomplete member name";
            }

            if (empty($member->address) || empty($member->postal_code) || empty($member->city)) {
                $errors[] = "Incomplete member address";
            }

            // Validate creditor identifier for active mandates
            if ($mandate->sepa_mandate_status === 'active' && empty($mandate->sepa_creditor_identifier)) {
                $errors[] = "Missing creditor identifier";
            }

            if (!empty($errors)) {
                $this->stats['validation_errors']++;

                if ($verboseLog) {
                    $this->warn("✗ Validation errors for member #{$mandate->member_id}:");
                    foreach ($errors as $error) {
                        $this->line("  - {$error}");
                    }
                }

                $validationErrors[$mandate->id] = $errors;

                if (!$dryRun) {
                    // Update mandate with validation errors
                    $mandate->update([
                        'sepa_mandate_data' => array_merge($mandate->sepa_mandate_data ?? [], [
                            'validation_errors' => $errors,
                            'last_validated_at' => now()->toDateTimeString()
                        ])
                    ]);
                }
            } else if ($verboseLog) {
                $this->info("✓ Mandate valid for member #{$mandate->member_id}");
            }
        }

        if (!empty($validationErrors)) {
            $this->warn("Found validation errors in {$this->stats['validation_errors']} mandates");

            // Send summary to admin
            if (!$dryRun) {
                $this->sendValidationErrorSummary($validationErrors);
            }
        }
    }

    /**
     * Create first payments after mandate activation
     */
    protected function createFirstPaymentsAfterActivation(?int $gymId, bool $dryRun, bool $verboseLog): void
    {
        // Find recently activated mandates without initial payment
        $recentlyActivated = PaymentMethod::sepa()
            ->where('sepa_mandate_status', 'active')
            ->where('updated_at', '>=', Carbon::now()->subDays(7))
            ->whereDoesntHave('member.payments', function($q) {
                $q->where('payment_method', 'sepa_direct_debit')
                  ->whereIn('status', ['pending', 'unknown', 'paid']);
            })
            ->with(['member', 'member.memberships.membershipPlan']);

        if ($gymId) {
            $recentlyActivated->whereHas('member', function($q) use ($gymId) {
                $q->where('gym_id', $gymId);
            });
        }

        $mandates = $recentlyActivated->get();

        $this->line("Found {$mandates->count()} activated mandates needing first payment");

        foreach ($mandates as $mandate) {
            try {
                $member = $mandate->member;
                $activeMembership = $member->memberships()
                    ->where('status', 'active')
                    ->first();

                if (!$activeMembership) {
                    if ($verboseLog) {
                        $this->line("⊘ No active membership for member #{$member->id}");
                    }
                    continue;
                }

                if ($dryRun) {
                    $this->line("[DRY RUN] Would create first payment for member #{$member->id}");
                    continue;
                }

                // Create first SEPA payment
                $payment = $this->paymentService->createPendingPayment(
                    $member,
                    $activeMembership,
                    $mandate
                );

                if ($payment) {
                    $this->stats['first_payments_created']++;

                    if ($verboseLog) {
                        $this->info("✓ Created first payment for {$member->full_name} ({$payment->formatted_amount})");
                    }

                    Log::info('First SEPA payment created after mandate activation', [
                        'payment_id' => $payment->id,
                        'member_id' => $member->id,
                        'mandate_id' => $mandate->id,
                    ]);
                }

            } catch (\Exception $e) {
                $this->error("Error creating first payment for member #{$mandate->member_id}: " . $e->getMessage());
                Log::error('First SEPA payment creation failed', [
                    'member_id' => $mandate->member_id,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
    }

    /**
     * Get creditor identifier for gym
     */
    protected function getCreditorId($gym): string
    {
        // This should be configured per gym
        // Format: DE98ZZZ09999999999 (example for Germany)
        return $gym->sepa_creditor_id ?? config('sepa.default_creditor_id', 'DE98ZZZ09999999999');
    }

    /**
     * Activate pending member and membership
     */
    protected function activatePendingMemberAndMembership(Member $member): void
    {
        DB::beginTransaction();
        try {
            // Activate member if pending
            if ($member->isPending()) {
                $member->activateMember();
            }

            // Activate membership if pending
            $activeMembership = $member->memberships()
                ->where('status', 'pending')
                ->first();

            if ($activeMembership) {
                $activeMembership->activateMembership();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate IBAN
     */
    protected function validateIBAN(?string $iban): bool
    {
        if (empty($iban)) {
            return false;
        }

        // Remove spaces and convert to uppercase
        $iban = strtoupper(str_replace(' ', '', $iban));

        // Check length (varies by country)
        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return false;
        }

        // Basic IBAN structure validation
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }

        // Checksum validation (mod 97)
        $chars = str_split($iban);
        $shifted = array_merge(array_slice($chars, 4), array_slice($chars, 0, 4));
        $iban_numeric = '';

        foreach ($shifted as $char) {
            if (is_numeric($char)) {
                $iban_numeric .= $char;
            } else {
                $iban_numeric .= ord($char) - ord('A') + 10;
            }
        }

        return bcmod($iban_numeric, '97') === '1';
    }

    /**
     * Determine reminder type based on days pending
     */
    protected function determineReminderType(int $daysPending): ?string
    {
        return match(true) {
            $daysPending === 1 => 'initial',
            $daysPending === 3 => 'first_reminder',
            $daysPending === 7 => 'second_reminder',
            $daysPending === 14 => 'final_reminder',
            $daysPending > 14 && $daysPending % 7 === 0 => 'weekly_reminder',
            default => null
        };
    }

    /**
     * Check if reminder was recently sent
     */
    protected function wasReminderRecentlySent(PaymentMethod $mandate, string $reminderType): bool
    {
        $metadata = $mandate->sepa_mandate_data ?? [];
        $reminderKey = "reminder_{$reminderType}_sent_at";

        if (isset($metadata[$reminderKey])) {
            $lastSent = Carbon::parse($metadata[$reminderKey]);
            return $lastSent->diffInDays(now()) < 3; // Don't send same reminder within 3 days
        }

        return false;
    }

    /**
     * Send mandate reminder email
     */
    protected function sendMandateReminder(PaymentMethod $mandate, string $reminderType): void
    {
        $member = $mandate->member;

        Mail::send('emails.sepa-mandate-reminder', [
            'member' => $member,
            'mandate' => $mandate,
            'reminderType' => $reminderType,
            'mandateReference' => $mandate->sepa_mandate_reference,
        ], function ($message) use ($member, $reminderType) {
            $subject = match($reminderType) {
                'initial' => 'SEPA-Lastschriftmandat - Unterschrift erforderlich',
                'first_reminder' => 'Erinnerung: SEPA-Lastschriftmandat ausstehend',
                'second_reminder' => '2. Erinnerung: Bitte unterzeichnen Sie Ihr SEPA-Mandat',
                'final_reminder' => 'Letzte Erinnerung: SEPA-Mandat läuft ab',
                default => 'SEPA-Lastschriftmandat - Aktion erforderlich'
            };

            $message->to($member->email)
                    ->subject($subject);
        });
    }

    /**
     * Update reminder metadata
     */
    protected function updateReminderMetadata(PaymentMethod $mandate, string $reminderType): void
    {
        $metadata = $mandate->sepa_mandate_data ?? [];
        $metadata["reminder_{$reminderType}_sent_at"] = now()->toDateTimeString();
        $metadata['last_reminder_type'] = $reminderType;
        $metadata['total_reminders_sent'] = ($metadata['total_reminders_sent'] ?? 0) + 1;

        $mandate->update(['sepa_mandate_data' => $metadata]);
    }

    /**
     * Send mandate activation confirmation
     */
    protected function sendMandateActivationConfirmation(PaymentMethod $mandate): void
    {
        try {
            $member = $mandate->member;

            Mail::send('emails.sepa-mandate-activated', [
                'member' => $member,
                'mandate' => $mandate,
                'mandateReference' => $mandate->sepa_mandate_reference,
                'creditorId' => $mandate->sepa_creditor_identifier,
            ], function ($message) use ($member) {
                $message->to($member->email)
                        ->subject('SEPA-Lastschriftmandat aktiviert');
            });

        } catch (\Exception $e) {
            Log::warning('Failed to send mandate activation confirmation', [
                'member_id' => $mandate->member_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send mandate expiration notice
     */
    protected function sendMandateExpirationNotice(PaymentMethod $mandate): void
    {
        try {
            $member = $mandate->member;

            Mail::send('emails.sepa-mandate-expired', [
                'member' => $member,
                'mandate' => $mandate,
            ], function ($message) use ($member) {
                $message->to($member->email)
                        ->subject('SEPA-Lastschriftmandat abgelaufen');
            });

        } catch (\Exception $e) {
            Log::warning('Failed to send mandate expiration notice', [
                'member_id' => $mandate->member_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check for alternative payment method
     */
    protected function checkAlternativePaymentMethod(Member $member): void
    {
        $activePaymentMethods = $member->activePaymentMethods()->count();

        if ($activePaymentMethods === 0) {
            // Member has no active payment methods
            Log::warning('Member has no active payment methods after SEPA expiration', [
                'member_id' => $member->id
            ]);

            // Set member status to pending if they have active membership
            if ($member->memberships()->where('status', 'active')->exists()) {
                $member->setPending('sepa_mandate_expired_no_alternative');
            }
        }
    }

    /**
     * Send validation error summary to admin
     */
    protected function sendValidationErrorSummary(array $validationErrors): void
    {
        try {
            $adminEmail = config('scheduler.notifications.admin_email');
            if (!$adminEmail) {
                return;
            }

            Mail::send('emails.admin.sepa-validation-errors', [
                'errors' => $validationErrors,
                'totalErrors' => count($validationErrors),
            ], function ($message) use ($adminEmail) {
                $message->to($adminEmail)
                        ->subject('[SEPA] Validation Errors Found');
            });

        } catch (\Exception $e) {
            Log::warning('Failed to send validation error summary', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Print processing summary
     */
    protected function printSummary(): void
    {
        $this->info("\n===========================================");
        $this->info("SEPA Processing Summary");
        $this->info("===========================================");

        if ($this->option('dry-run')) {
            $this->info("MODE: DRY RUN - No actual changes made");
            $this->info("");
        }

        $this->info("Mandates:");
        $this->info("  - Total processed: {$this->stats['total_processed']}");
        $this->info("  - Pending mandates: {$this->stats['pending_mandates']}");
        $this->info("  - Activated: {$this->stats['activated_mandates']}");
        $this->info("  - Expired: {$this->stats['expired_mandates']}");

        $this->info("\nActions:");
        $this->info("  - Reminders sent: {$this->stats['reminders_sent']}");
        $this->info("  - Validation errors: {$this->stats['validation_errors']}");
        $this->info("  - First payments created: {$this->stats['first_payments_created']}");

        $this->info("===========================================\n");

        if (!$this->option('dry-run')) {
            Log::info('SEPA mandate processing completed', $this->stats);
        }
    }
}
