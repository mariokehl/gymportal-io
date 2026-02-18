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
    public function __construct(
        private readonly MollieService $mollieService,
    ) {}

    /**
     * Creates pending payments for a new membership
     */
    public function createPendingPayment(
        Member $member,
        Membership $membership,
        ?PaymentMethod $paymentMethod,
        ?Carbon $billingAnchorDate = null
    ): ?Payment {
        $plan = $membership->membershipPlan;

        // Keine Zahlung erstellen, wenn Startdatum in der Vergangenheit liegt
        // und kein billing_anchor_date angegeben wurde
        $today = Carbon::today();
        if ($membership->start_date->lt($today) && !$billingAnchorDate) {
            return null;
        }

        // Specify payment details based on plan and payment method
        $paymentDetails = $this->calculatePaymentDetails($plan, $membership, $paymentMethod, $billingAnchorDate);

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
                    'billing_anchor_date' => $billingAnchorDate?->toDateString(),
                ],
            ]);

            // Create invoice if necessary
            if ($this->shouldCreateInvoice($paymentMethod?->type)) {
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

        // Keine Aktivierungsgebühr berechnen, wenn Startdatum in der Vergangenheit liegt
        $today = Carbon::today();
        if ($membership->start_date->lt($today)) {
            return null;
        }

        try {
            $payment = Payment::create([
                'gym_id' => $member->gym_id,
                'membership_id' => $membership->id,
                'member_id' => $member->id,
                'amount' => $plan->setup_fee,
                'currency' => 'EUR',
                'description' => "Aktivierungsgebühr",
                'status' => $this->determineInitialPaymentStatus($paymentMethod?->type),
                'payment_method' => $paymentMethod?->type,
                'execution_date' => $this->calculateInitialExecutionDate($membership, $paymentMethod?->type),
                'due_date' => $membership->start_date,
                'notes' => 'Einmalige Aktivierungsgebühr bei Vertragsabschluss',
                'metadata' => [
                    'membership_plan_id' => $plan->id,
                    'payment_type' => 'setup_fee',
                    'created_via' => $member->registration_source ?? 'manual',
                    'payment_method_id' => $paymentMethod?->id,
                ],
            ]);

            if ($this->shouldCreateInvoice($paymentMethod?->type)) {
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
     * Erstellt die nächste wiederkehrende Zahlung
     *
     * @param Member $member
     * @param Membership $membership
     * @param Carbon|null $nextPaymentDate
     * @return Payment
     */
    public function createNextRecurringPayment(
        Member $member,
        Membership $membership,
        ?Carbon $nextPaymentDate = null
    ): Payment {
        $payments = $this->createRecurringPayments($member, $membership, 1, $nextPaymentDate);
        return $payments[0];
    }

    /**
     * Erstellt wiederkehrende Zahlungen für die nächsten Monate
     *
     * @param Member $member Das Mitglied für das die Zahlungen erstellt werden
     * @param Membership $membership Die Mitgliedschaft
     * @param int $monthsAhead Anzahl der Monate im Voraus (Standard: 3)
     * @param Carbon|null $nextPaymentDate Optionales Datum für die nächste Zahlung
     * @return Payment[] Array aus Payment Models
     * @throws \Exception Wenn die Erstellung der Zahlungen fehlschlägt
     */
    public function createRecurringPayments(
        Member $member,
        Membership $membership,
        int $monthsAhead = 3,
        ?Carbon $nextPaymentDate = null
    ): array {
        $plan = $membership->membershipPlan;
        $payments = [];

        // Berechne Startdatum für wiederkehrende Zahlungen
        $startDate = $nextPaymentDate ?? $membership->start_date;

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
                    'execution_date' => $this->calculateRecurringExecutionDate($currentDate, $paymentMethod?->type),
                    'due_date' => $currentDate->toDateString(),
                    'notes' => "Wiederkehrende Zahlung für {$plan->billing_cycle_text}",
                    'metadata' => [
                        'membership_plan_id' => $plan->id,
                        'payment_type' => 'recurring',
                        'billing_cycle' => $plan->billing_cycle,
                        'billing_period_start' => $currentDate->toDateString(),
                        'billing_period_end' => $this->calculateBillingPeriodEnd($currentDate, $plan->billing_cycle)->toDateString(),
                        'created_via' => 'scheduler',
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
        ?PaymentMethod $paymentMethod,
        ?Carbon $billingAnchorDate = null
    ): ?array {
        $paymentMethodType = $paymentMethod?->type ?? 'unknown';

        // Use billing_anchor_date if provided, otherwise use start_date
        $dueDate = $billingAnchorDate ?? $membership->start_date;

        // First payment (may include trial period)
        $amount = $plan->trial_period_days > 0 && $plan->trial_price !== null
            ? $plan->trial_price
            : $plan->price;

        $description = $plan->trial_period_days > 0 && $plan->trial_price !== null
            ? "Probezeitraum ({$plan->trial_period_days} Tage): {$plan->name}"
            : "1. Mitgliedsbeitrag: {$plan->name}";

        // If billing anchor date is different from start date, adjust description
        if ($billingAnchorDate && !$billingAnchorDate->isSameDay($membership->start_date)) {
            $description = "1. Mitgliedsbeitrag (ab " . $billingAnchorDate->format('d.m.Y') . "): {$plan->name}";
        }

        $executionDate = $this->calculateInitialExecutionDate($membership, $paymentMethodType, $billingAnchorDate);
        $notes = $this->generatePaymentNotes($membership, $paymentMethod?->type_text);

        return [
            'amount' => $amount,
            'description' => $description,
            'execution_date' => $executionDate,
            'due_date' => $dueDate,
            'notes' => $notes,
            'type' => $plan->trial_period_days > 0 ? 'trial' : 'initial',
        ];
    }

    /**
     * Determines initial payment status based on payment type
     */
    private function determineInitialPaymentStatus(?string $paymentMethod): string
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
    private function calculateInitialExecutionDate(Membership $membership, ?string $paymentMethod, ?Carbon $billingAnchorDate = null): Carbon
    {
        // Use billing anchor date if provided, otherwise use start_date
        $baseDate = $billingAnchorDate ?? $membership->start_date;

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
     * Calculates execution date for recurring payments
     */
    private function calculateRecurringExecutionDate(Carbon $billingDate, string $paymentMethod): Carbon
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
    private function shouldCreateInvoice(?string $paymentMethod): bool
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
     *
     * @deprecated Diese Funktion wird aktuell nicht verwendet, da die Logik in PaymentController->markAsPaid ausgelagert wurde
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
     *
     * @deprecated Diese Funktion wird aktuell nicht verwendet, da die Logik in PaymentController->cancel ausgelagert wurde
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

    /**
     * Zentrale Methode für Widerruf: Storniert ausstehende Zahlungen
     * und initiiert Erstattungen falls bereits bezahlt wurde.
     *
     * @param Membership $membership Die widerrufene Mitgliedschaft
     * @return float Der Erstattungsbetrag
     */
    public function handleWithdrawalPayments(Membership $membership): float
    {
        try {
            // 1. Ausstehende Zahlungen ohne mollie_payment_id und transaction_id immer stornieren
            $canceledCount = $this->cancelPendingPayments($membership);

            // 2. Erstattung initiieren falls bereits bezahlt wurde
            $refundAmount = (float) $membership->payments()
                ->whereIn('status', ['paid', 'completed'])
                ->sum('amount');

            if ($refundAmount > 0) {
                $this->initiateRefund($membership, $refundAmount);
            }

            Log::info('Withdrawal payments handled', [
                'membership_id' => $membership->id,
                'canceled_pending' => $canceledCount,
                'refund_amount' => $refundAmount,
            ]);

            return $refundAmount;

        } catch (\Exception $e) {
            Log::error('Failed to handle withdrawal payments', [
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Storniert alle ausstehenden Zahlungen ohne mollie_payment_id
     * und/oder transaction_id für eine Mitgliedschaft.
     *
     * @param Membership $membership
     * @return int Anzahl der stornierten Zahlungen
     */
    private function cancelPendingPayments(Membership $membership): int
    {
        return $membership->payments()
            ->where('status', 'pending')
            ->whereNull('mollie_payment_id')
            ->whereNull('transaction_id')
            ->update([
                'status' => 'canceled',
                'notes' => DB::raw("CONCAT(COALESCE(notes, ''), ' | Storniert aufgrund Widerruf am " . now()->format('d.m.Y H:i') . "')"),
            ]);
    }

    /**
     * Initiiert Erstattung für Widerruf gemäß § 356a BGB
     *
     * Bei einem Widerruf müssen alle geleisteten Zahlungen innerhalb von
     * 14 Tagen erstattet werden.
     *
     * @param Membership $membership Die widerrufene Mitgliedschaft
     * @param float $refundAmount Der zu erstattende Betrag
     * @return bool True wenn Erstattung erfolgreich initiiert wurde
     */
    public function initiateRefund(Membership $membership, float $refundAmount): bool
    {
        if ($refundAmount <= 0) {
            return true;
        }

        try {
            // Alle bezahlten Zahlungen für diese Mitgliedschaft abrufen
            $paidPayments = $membership->payments()
                ->whereIn('status', ['paid', 'completed'])
                ->orderBy('paid_date', 'desc')
                ->get();

            $remainingRefund = $refundAmount;

            foreach ($paidPayments as $payment) {
                if ($remainingRefund <= 0) {
                    break;
                }

                $paymentRefundAmount = min($payment->amount, $remainingRefund);

                if ($payment->isMolliePaymentMethod() && $payment->mollie_payment_id) {
                    // Mollie-Zahlung: Refund direkt über die Mollie-API auslösen.
                    // Es wird kein eigenes Payment angelegt und der lokale Status bleibt auf "paid".
                    // Mollie sendet anschließend automatisch einen Webhook, welcher alle
                    // weiteren Aktionen im System ausführt (Refund-Eintrag, negatives Payment,
                    // Status-Update der Original-Zahlung).
                    $this->mollieService->createRefund(
                        $membership->member->gym,
                        $payment->mollie_payment_id,
                        $paymentRefundAmount,
                        "Erstattung Widerruf gemäß § 356a BGB - Ref: #{$payment->id}",
                    );

                    Log::info('Mollie refund initiated via API', [
                        'payment_id' => $payment->id,
                        'mollie_payment_id' => $payment->mollie_payment_id,
                        'amount' => $paymentRefundAmount,
                    ]);
                } else {
                    // Nicht-Mollie-Zahlung: Erstattungszahlung lokal erstellen
                    Payment::create([
                        'gym_id' => $payment->gym_id,
                        'membership_id' => $membership->id,
                        'member_id' => $membership->member_id,
                        'amount' => -$paymentRefundAmount, // Negativer Betrag für Erstattung
                        'currency' => $payment->currency,
                        'description' => "Erstattung Widerruf - Ref: #{$payment->id}",
                        'status' => 'pending',
                        'payment_method' => $payment->payment_method,
                        'execution_date' => now()->addDays(14), // Max. 14 Tage gemäß § 356a BGB
                        'due_date' => now()->addDays(14),
                        'transaction_id' => $payment->id, // Verknüpfung zur Original-Zahlung
                        'notes' => "Erstattung aufgrund Widerruf gemäß § 356a BGB | Original-Zahlung: #{$payment->id}",
                        'metadata' => [
                            'payment_type' => 'withdrawal_refund',
                            'original_payment_id' => $payment->id,
                            'withdrawal_date' => now()->toIso8601String(),
                            'membership_id' => $membership->id,
                        ],
                    ]);

                    // Original-Zahlung als erstattet markieren (nur bei Nicht-Mollie)
                    $payment->update([
                        'status' => 'refunded',
                        'notes' => $payment->notes . ' | Erstattet aufgrund Widerruf am ' . now()->format('d.m.Y H:i'),
                    ]);
                }

                $remainingRefund -= $paymentRefundAmount;
            }

            Log::info('Refund initiated for withdrawal', [
                'membership_id' => $membership->id,
                'refund_amount' => $refundAmount,
                'payments_refunded' => $paidPayments->count(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to initiate refund for withdrawal', [
                'membership_id' => $membership->id,
                'refund_amount' => $refundAmount,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
