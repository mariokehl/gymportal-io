<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentService
{
    /**
     * Creates pending payments for a new membership
     */
    public function createPendingPayment(
        Member $member,
        Membership $membership,
        ?PaymentMethod $paymentMethod
    ): ?Payment {
        $plan = $membership->membershipPlan;

        // Specify payment details based on plan and payment method
        $paymentDetails = $this->calculatePaymentDetails($plan, $membership, $paymentMethod);

        if (!$paymentDetails) {
            return null;
        }

        DB::beginTransaction();

        try {
            // Create payment
            $payment = Payment::create([
                'gym_id' => $member->gym_id,
                'membership_id' => $membership->id,
                'member_id' => $member->id,
                'amount' => $paymentDetails['amount'],
                'currency' => 'EUR',
                'description' => $paymentDetails['description'],
                'status' => $this->determineInitialPaymentStatus($paymentMethod?->type),
                'payment_method' => $paymentMethod?->type,
                'execution_date' => $paymentDetails['execution_date'],
                'due_date' => $paymentDetails['due_date'],
                'notes' => $paymentDetails['notes'],
                'metadata' => [
                    'membership_plan_id' => $plan->id,
                    'payment_type' => $paymentDetails['type'],
                    'billing_cycle' => $plan->billing_cycle,
                    'created_via' => $member->registration_source ?? 'manual',
                    'payment_method_id' => $paymentMethod?->id,
                ],
            ]);

            // Create invoice if necessary
            if ($this->shouldCreateInvoice($paymentMethod)) {
                $this->createInvoiceForPayment($payment);
            }

            DB::commit();

            // Analytics Event
            $this->trackPaymentCreation($payment, $member->gym);

            // Further actions based on payment method
            $this->handlePaymentMethodSpecificActions($payment, $paymentMethod);

            return $payment;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment creation failed', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
                'payment_method' => $paymentMethod
            ]);

            throw $e;
        }
    }

    /**
     * Erstellt Setup-Gebühr als separate Zahlung
     */
    public function createSetupFeePayment(
        Member $member,
        Membership $membership,
        ?PaymentMethod $paymentMethod
    ): ?Payment {
        $plan = $membership->membershipPlan;

        if (!$plan->setup_fee || $plan->setup_fee <= 0) {
            return null;
        }

        try {
            $payment = Payment::create([
                'gym_id' => $member->gym_id,
                'membership_id' => $membership->id,
                'member_id' => $member->id,
                'amount' => $plan->setup_fee,
                'currency' => 'EUR',
                'description' => "Einrichtungsgebühr - {$plan->name}",
                'status' => $this->determineInitialPaymentStatus($paymentMethod?->type),
                'payment_method' => $paymentMethod?->type,
                'due_date' => $this->calculateSetupFeeDueDate($paymentMethod?->type),
                'notes' => 'Einmalige Einrichtungsgebühr bei Vertragsabschluss',
                'metadata' => [
                    'membership_plan_id' => $plan->id,
                    'payment_type' => 'setup_fee',
                    'created_via' => $member->registration_source ?? 'manual',
                    'payment_method_id' => $paymentMethod?->id,
                ],
            ]);

            if ($this->shouldCreateInvoice($paymentMethod)) {
                $this->createInvoiceForPayment($payment);
            }

            return $payment;

        } catch (\Exception $e) {
            Log::error('Setup fee payment creation failed', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'setup_fee' => $plan->setup_fee,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Erstellt wiederkehrende Zahlungen für die nächsten Monate
     */
    public function createRecurringPayments(
        Member $member,
        Membership $membership,
        int $monthsAhead = 3
    ): array {
        $plan = $membership->membershipPlan;
        $payments = [];

        // Berechne Startdatum für wiederkehrende Zahlungen
        $startDate = $membership->start_date;

        // Berücksichtige Probezeit
        if ($plan->trial_period_days > 0) {
            $startDate = $startDate->copy()->addDays($plan->trial_period_days);
        }

        $currentDate = $startDate->copy();
        $paymentMethod = $member->defaultPaymentMethod;

        try {
            for ($i = 0; $i < $monthsAhead; $i++) {
                // Überspringe wenn Mitgliedschaft bereits beendet ist
                if ($membership->end_date && $currentDate->gt($membership->end_date)) {
                    break;
                }

                $payment = Payment::create([
                    'gym_id' => $member->gym_id,
                    'membership_id' => $membership->id,
                    'member_id' => $member->id,
                    'amount' => $plan->price,
                    'currency' => 'EUR',
                    'description' => $this->generateRecurringPaymentDescription($plan, $currentDate),
                    'status' => 'pending',
                    'payment_method' => $paymentMethod?->type,
                    'due_date' => $this->calculateRecurringDueDate($currentDate, $paymentMethod),
                    'notes' => "Wiederkehrende Zahlung für {$plan->billing_cycle_text}",
                    'metadata' => [
                        'membership_plan_id' => $plan->id,
                        'payment_type' => 'recurring',
                        'billing_cycle' => $plan->billing_cycle,
                        'billing_period_start' => $currentDate->toDateString(),
                        'billing_period_end' => $this->calculateBillingPeriodEnd($currentDate, $plan->billing_cycle)->toDateString(),
                        'payment_method_id' => $paymentMethod?->id,
                    ],
                ]);

                $payments[] = $payment;

                // Nächstes Datum berechnen basierend auf Billing Cycle
                $currentDate = $this->calculateNextBillingDate($currentDate, $plan->billing_cycle);
            }

            return $payments;

        } catch (\Exception $e) {
            Log::error('Recurring payments creation failed', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'months_ahead' => $monthsAhead,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Calculates payment details based on plan and payment type
     */
    private function calculatePaymentDetails(
        MembershipPlan $plan,
        Membership $membership,
        ?PaymentMethod $paymentMethod
    ): ?array {
        $paymentMethodType = $paymentMethod?->type ?? 'unknown';

        // First payment (may include trial period)
        $amount = $plan->trial_period_days > 0 && $plan->trial_price !== null
            ? $plan->trial_price
            : $plan->price;

        $description = $plan->trial_period_days > 0 && $plan->trial_price !== null
            ? "Probezeitraum ({$plan->trial_period_days} Tage): {$plan->name}"
            : "1. Mitgliedsbeitrag: {$plan->name}";

        $executionDate = $this->calculateInitialExecutionDate($membership, $paymentMethodType);
        $notes = $this->generatePaymentNotes($membership, $paymentMethod?->type_text);

        return [
            'amount' => $amount,
            'description' => $description,
            'execution_date' => $executionDate,
            'due_date' => $membership->start_date,
            'notes' => $notes,
            'type' => $plan->trial_period_days > 0 ? 'trial' : 'initial',
        ];
    }

    /**
     * Determines initial payment status based on payment type
     */
    private function determineInitialPaymentStatus(string $paymentMethod): string
    {
        return match($paymentMethod) {
            'cash' => 'pending',
            'sepa_direct_debit' => 'pending',
            'banktransfer' => 'pending',
            'invoice' => 'pending',
            'standingorder' => 'pending',
            default => 'pending',
        };
    }

    /**
     * Calculates execution date for initial payment
     */
    private function calculateInitialExecutionDate(Membership $membership, string $paymentMethod): Carbon
    {
        $baseDate = $membership->start_date;

        return match($paymentMethod) {
            'cash' => $baseDate,
            'sepa_direct_debit' => $baseDate->copy()->addDays(3),
            'banktransfer' => $baseDate->copy()->addDays(7),
            'invoice' => $baseDate->copy()->addDays(14),
            'standingorder' => $baseDate->copy()->addDays(30),
            'mollie_directdebit' => $baseDate->copy()->addDays(5),
            default => $baseDate->copy()->addDays(1),
        };
    }

    /**
     * Calculates execution date for setup fee
     */
    private function calculateSetupFeeDueDate(string $paymentMethod): Carbon
    {
        return match($paymentMethod) {
            'cash' => now(),
            'sepa_direct_debit' => now()->addDays(3),
            'banktransfer' => now()->addDays(7),
            'invoice' => now()->addDays(14),
            'standingorder' => now()->addDays(30),
            'mollie_directdebit' => now()->addDays(5),
            default => now()->addDay(),
        };
    }

    /**
     * Calculates execution date for recurring payments
     */
    private function calculateRecurringDueDate(Carbon $billingDate, string $paymentMethod): Carbon
    {
        return match($paymentMethod) {
            'cash' => $billingDate,
            'sepa_direct_debit' => $billingDate->copy()->subDays(2),
            'banktransfer' => $billingDate->copy()->subDays(5),
            'invoice' => $billingDate,
            'standingorder' => $billingDate->copy()->subDays(3),
            'mollie_directdebit' => $billingDate->copy()->subDays(1),
            default => $billingDate,
        };
    }

    /**
     * Berechnet nächstes Billing-Datum
     */
    private function calculateNextBillingDate(Carbon $currentDate, string $billingCycle): Carbon
    {
        return match($billingCycle) {
            'monthly' => $currentDate->copy()->addMonth(),
            'quarterly' => $currentDate->copy()->addMonths(3),
            'yearly' => $currentDate->copy()->addYear(),
            default => $currentDate->copy()->addMonth(),
        };
    }

    /**
     * Berechnet Ende der Billing-Periode
     */
    private function calculateBillingPeriodEnd(Carbon $startDate, string $billingCycle): Carbon
    {
        return match($billingCycle) {
            'monthly' => $startDate->copy()->addMonth()->subDay(),
            'quarterly' => $startDate->copy()->addMonths(3)->subDay(),
            'yearly' => $startDate->copy()->addYear()->subDay(),
            default => $startDate->copy()->addMonth()->subDay(),
        };
    }

    /**
     * Generiert Beschreibung für wiederkehrende Zahlungen
     */
    private function generateRecurringPaymentDescription(MembershipPlan $plan, Carbon $billingDate): string
    {
        $periodText = match($plan->billing_cycle) {
            'monthly' => $billingDate->format('m/Y'),
            'quarterly' => 'Q' . $billingDate->quarter . '/' . $billingDate->year,
            'yearly' => $billingDate->year,
            default => $billingDate->format('m/Y'),
        };

        return "Mitgliedsbeitrag {$periodText} - {$plan->name}";
    }

    /**
     * Generates notes for payment
     */
    private function generatePaymentNotes(Membership $membership, ?string $paymentMethodTypeText): string
    {
        $notes = [];

        if (isset($paymentMethodTypeText)) {
            $notes[] = "Zahlungsart: {$paymentMethodTypeText}";
        }

        $plan = $membership->membershipPlan;
        if ($plan->trial_period_days > 0) {
            $notes[] = "Probezeit: {$plan->trial_period_days} Tage";
        }

        if ($plan->commitment_months > 0) {
            $notes[] = "Laufzeit: {$plan->commitment_months} Monate";
        }

        return implode(' | ', $notes);
    }

    /**
     * Prüft ob Invoice erstellt werden soll
     */
    private function shouldCreateInvoice(string $paymentMethod): bool
    {
        return in_array($paymentMethod, ['invoice', 'banktransfer', 'standingorder']);
    }

    /**
     * Erstellt Invoice für Payment
     */
    private function createInvoiceForPayment(Payment $payment): Invoice
    {
        // Dies würde den InvoiceService verwenden
        // Hier vereinfachte Implementierung

        $invoiceNumber = $this->generateInvoiceNumber($payment->gym_id);

        return Invoice::create([
            'gym_id' => $payment->gym_id,
            'member_id' => $payment->member_id,
            'payment_id' => $payment->id,
            'invoice_number' => $invoiceNumber,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'pending',
            'due_date' => $payment->due_date,
            'description' => $payment->description,
            'issued_at' => now(),
        ]);
    }

    /**
     * Generiert Rechnungsnummer
     */
    private function generateInvoiceNumber(int $gymId): string
    {
        $prefix = 'R' . str_pad($gymId, 3, '0', STR_PAD_LEFT);
        $year = date('Y');
        $month = date('m');

        $lastNumber = Invoice::where('gym_id', $gymId)
            ->where('invoice_number', 'like', $prefix . $year . $month . '%')
            ->orderBy('invoice_number', 'desc')
            ->value('invoice_number');

        if ($lastNumber) {
            $lastSequence = intval(substr($lastNumber, -4));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix . $year . $month . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Behandelt zahlungsartspezifische Aktionen
     */
    private function handlePaymentMethodSpecificActions(Payment $payment, ?PaymentMethod $paymentMethod): void
    {
        switch ($payment->payment_method) {
            case 'sepa_direct_debit':
                $this->handleSepaPayment($payment, $paymentMethod);
                break;

            case 'cash':
                $this->handleCashPayment($payment);
                break;

            case 'banktransfer':
                $this->handleBankTransferPayment($payment);
                break;

            case 'invoice':
                $this->handleInvoicePayment($payment);
                break;
        }
    }

    /**
     * SEPA-spezifische Behandlung
     */
    private function handleSepaPayment(Payment $payment, ?PaymentMethod $paymentMethod): void
    {
        if (!$paymentMethod || !$paymentMethod->requiresSepaMandate()) {
            return;
        }

        // Prüfe SEPA-Mandat Status
        if (!$paymentMethod->isSepaMandateValid()) {
            $payment->update([
                'status' => 'pending',
                'notes' => $payment->notes . ' | Wartet auf SEPA-Mandat'
            ]);
        }

        // Log für SEPA-Verarbeitung
        Log::info('SEPA payment created', [
            'payment_id' => $payment->id,
            'mandate_status' => $paymentMethod->sepa_mandate_status,
            'mandate_reference' => $paymentMethod->sepa_mandate_reference,
        ]);
    }

    /**
     * Barzahlungs-spezifische Behandlung
     */
    private function handleCashPayment(Payment $payment): void
    {
        // Bei Barzahlung kann das Payment als "vor Ort zu zahlen" markiert werden
        $payment->update([
            'notes' => $payment->notes . ' | Zahlung vor Ort beim nächsten Besuch'
        ]);
    }

    /**
     * Banküberweisung-spezifische Behandlung
     */
    private function handleBankTransferPayment(Payment $payment): void
    {
        // Hier könnte eine E-Mail mit Überweisungsdaten gesendet werden
        $payment->update([
            'notes' => $payment->notes . ' | Überweisungsdaten per E-Mail versendet'
        ]);
    }

    /**
     * Rechnungs-spezifische Behandlung
     */
    private function handleInvoicePayment(Payment $payment): void
    {
        // Hier könnte die Rechnung automatisch versendet werden
        $payment->update([
            'notes' => $payment->notes . ' | Rechnung wird erstellt und versendet'
        ]);
    }

    /**
     * Analytics Event für Payment-Erstellung
     */
    private function trackPaymentCreation(Payment $payment, Gym $gym): void
    {
        try {
            // Dies würde den AnalyticsService verwenden
            Log::info('Payment created', [
                'gym_id' => $gym->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'member_id' => $payment->member_id,
                'membership_id' => $payment->membership_id,
            ]);
        } catch (\Exception $e) {
            // Analytics-Fehler nicht weiterleiten
            Log::warning('Payment analytics tracking failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id
            ]);
        }
    }

    /**
     * Markiert Payment als bezahlt
     */
    public function markPaymentAsPaid(Payment $payment, array $metadata = []): bool
    {
        try {
            $payment->update([
                'status' => 'paid',
                'paid_date' => now()->toDateString(),
                'metadata' => array_merge($payment->metadata ?? [], $metadata)
            ]);

            // Membership aktivieren falls pending
            if ($payment->membership && $payment->membership->status === 'pending') {
                $payment->membership->activateMembership();
            }

            // Member aktivieren falls pending
            if ($payment->member && $payment->member->status === 'pending') {
                $payment->member->activateMember();
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to mark payment as paid', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Storniert Payment
     */
    public function cancelPayment(Payment $payment, ?string $reason = null): bool
    {
        try {
            $payment->update([
                'status' => 'failed',
                'failed_at' => now(),
                'notes' => $payment->notes . ' | Storniert: ' . ($reason ?? 'Nicht spezifiziert')
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to cancel payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
