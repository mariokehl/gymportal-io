<?php

// app/Http/Controllers/MemberPaymentController.php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberCreditLedger;
use App\Models\Payment;
use App\Services\CreditLedgerService;
use App\Services\MollieService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Exceptions\ApiException;

class MemberPaymentController extends Controller
{
    use AuthorizesRequests;

    protected MollieService $mollieService;

    protected CreditLedgerService $creditLedger;

    public function __construct(MollieService $mollieService, CreditLedgerService $creditLedger)
    {
        $this->mollieService = $mollieService;
        $this->creditLedger = $creditLedger;
    }

    public function store(Request $request, Member $member)
    {
        $this->authorize('update', $member);

        // Accept a localized decimal separator (e.g. "300,00") from the amount input.
        if ($request->filled('amount') && is_string($request->input('amount'))) {
            $request->merge([
                'amount' => str_replace(',', '.', $request->input('amount')),
            ]);
        }

        $validated = $request->validate([
            'payment_type' => 'nullable|in:regular,topup',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string',
            'due_date' => 'nullable|date',
            'paid_date' => 'nullable|date',
            'payment_method' => 'nullable|string',
            'status' => 'required|in:pending,paid',
            'notes' => 'nullable|string',
        ]);

        if (($validated['payment_type'] ?? 'regular') === 'topup') {
            return $this->storeTopup($request, $member, $validated);
        }

        $member->payments()->create([
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'due_date' => $validated['due_date'] ?? null,
            'paid_date' => $validated['paid_date'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'gym_id' => $member->gym_id,
            'membership_id' => $member->memberships()->first()?->id,
            'member_id' => $member->id,
        ]);

        return redirect()->back()->with('message', 'Zahlung wurde hinzugefügt.');
    }

    /**
     * Book a top-up. For manual methods (cash, bank transfer, invoice) the
     * credit is granted immediately. For a Mollie method the top-up stays
     * pending and the balance is only credited once Mollie confirms the payment
     * via webhook (see MollieWebhookController::handlePaymentPaid).
     */
    protected function storeTopup(Request $request, Member $member, array $validated)
    {
        $this->authorize('manageCredit', $member);

        $amountCents = $this->creditLedger->toCents($validated['amount']);

        if ($amountCents <= 0) {
            return redirect()->back()->with('error', 'Der Aufladebetrag muss größer als 0 sein.');
        }

        $paymentMethod = $validated['payment_method'] ?? 'banktransfer';
        $isMollie = str_starts_with($paymentMethod, 'mollie_');

        DB::beginTransaction();

        try {
            $paidDate = $validated['paid_date'] ?? now();
            $creator = $request->user();

            // Historic record of the top-up so it shows up in the payment history.
            $payment = $member->payments()->create([
                'amount' => $amountCents / 100,
                'description' => $validated['description'],
                'due_date' => $paidDate,
                // Manual methods are settled at once; Mollie stays pending.
                'paid_date' => $isMollie ? null : $paidDate,
                'payment_method' => $paymentMethod,
                'is_credit_topup' => true,
                'status' => $isMollie ? 'pending' : 'paid',
                'notes' => $validated['notes'] ?? null,
                'gym_id' => $member->gym_id,
                'membership_id' => $member->memberships()->first()?->id,
                'member_id' => $member->id,
                'metadata' => [
                    'created_by_name' => $creator?->fullName(),
                ],
            ]);

            if ($isMollie) {
                if (! $this->mollieService->isConfigured($member->gym)) {
                    DB::rollBack();

                    return redirect()->back()->with('error', 'Mollie ist für dieses Gym nicht konfiguriert.');
                }

                // Create a payment link so the member can pay without a stored
                // mandate. The webhook grants the credit once it is paid.
                $this->mollieService->createPaymentLink($payment);

                DB::commit();

                return redirect()->back()->with('message', 'Guthaben-Aufladung wurde als ausstehend vermerkt und wird nach Zahlungseingang gutgeschrieben.');
            }

            $this->creditLedger->credit(
                member: $member,
                amountCents: $amountCents,
                type: MemberCreditLedger::TYPE_TOPUP,
                description: $validated['description'],
                payment: $payment,
                createdBy: $creator,
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Guthaben-Aufladung fehlgeschlagen', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Guthaben-Aufladung konnte nicht verbucht werden.');
        }

        return redirect()->back()->with('message', 'Guthaben wurde aufgeladen.');
    }

    public function execute(Request $request, Member $member, Payment $payment)
    {
        // Validierung
        if ($payment->member_id !== $member->id) {
            return redirect()->back()->with('error', 'Zahlung gehört nicht zu diesem Mitglied.');
        }

        if (! in_array($payment->status, ['pending', 'unknown'])) {
            return redirect()->back()->with('error', 'Nur ausstehende Zahlungen können ausgeführt werden.');
        }

        DB::beginTransaction();

        try {
            // Apply stored credit first. If it fully covers the payment, the
            // payment is settled and no collection over a payment method is
            // needed. Otherwise only the remaining amount is collected over the
            // payment method.
            $creditResult = $this->creditLedger->settlePayment($payment, $request->user());

            if ($creditResult['fully_covered']) {
                DB::commit();

                return $this->createResponseWithMessage($member, 'Zahlung wurde vollständig über das Guthaben verrechnet.');
            }

            $payment->refresh();

            // When "Guthaben" was selected as the method, the remaining amount is
            // collected over the member's default payment method. Switch to it so
            // the lookup below finds a real (non-credit) method.
            if ($payment->payment_method === 'credit') {
                $defaultMethod = $member->paymentMethods()
                    ->where('status', 'active')
                    ->where('is_default', true)
                    ->first()
                    ?? $member->paymentMethods()
                        ->where('status', 'active')
                        ->where('type', '!=', 'credit')
                        ->first();

                if (! $defaultMethod) {
                    DB::rollBack();

                    return redirect()->back()->with('error', 'Guthaben deckt die Zahlung nicht vollständig und es ist keine Standard-Zahlungsmethode für den Restbetrag hinterlegt.');
                }

                $payment->update(['payment_method' => $defaultMethod->type]);
                $payment->refresh();
            }

            // Zahlungsmethode des Mitglieds abrufen
            $paymentMethod = $member->paymentMethods()
                ->where('status', 'active')
                ->where('type', $payment->payment_method)
                ->first();

            // Prüfe ob es die Zahlungsmethode überhaupt gibt
            if (! $paymentMethod) {
                DB::rollBack();

                return redirect()->back()->with('error', 'Keine Zahlungsart für die Zahlungsmethode hinterlegt.');
            }

            // Prüfe ob es eine Standard-Zahlungsmethode ist (nicht Mollie)
            if (! str_starts_with($paymentMethod->type, 'mollie_')) {
                return $this->executeStandardPayment($payment, $paymentMethod->type);
            }

            // Ab hier: Mollie-Zahlungsmethoden
            // Prüfen ob Mollie konfiguriert ist
            if (! $this->mollieService->isConfigured($member->gym)) {
                DB::rollBack();

                return redirect()->back()->with('error', 'Mollie ist für dieses Gym nicht konfiguriert.');
            }

            // Mollie Customer ID prüfen
            if (! $paymentMethod->mollie_customer_id) {
                DB::rollBack();

                return redirect()->back()->with('error', 'Kein Mollie-Kunde für dieses Mitglied gefunden.');
            }

            // Mollie-Zahlung erstellen
            $this->mollieService->createPaymentWithoutStoring($member, $payment, $paymentMethod);

            DB::commit();

            $this->createResponseWithMessage($member, 'Zahlung wird über Mollie ausgeführt.');

        } catch (ApiException $e) {
            DB::rollBack();
            Log::error('Mollie API Fehler beim Ausführen der Zahlung', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Mollie-Fehler: '.$e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fehler beim Ausführen der Zahlung', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Zahlung konnte nicht ausgeführt werden.');
        }
    }

    /**
     * Führe Standard-Zahlungsmethoden aus (nicht Mollie)
     */
    protected function executeStandardPayment(Payment $payment, string $paymentMethod)
    {
        try {
            $updateData = [
                'payment_method' => $paymentMethod,
            ];

            // Prüfe Zahlungsmethode und setze entsprechenden Status
            switch ($paymentMethod) {
                case 'cash':
                    // Barzahlung wird direkt als bezahlt markiert
                    $updateData['status'] = 'paid';
                    $updateData['paid_date'] = now();
                    $updateData['notes'] = ($payment->notes ? $payment->notes."\n" : '').
                                          'Barzahlung erhalten am '.now()->format('d.m.Y H:i');
                    $message = 'Barzahlung wurde als erhalten markiert.';
                    break;

                case 'banktransfer':
                    // Banküberweisung wird als bezahlt markiert (manuell überprüft)
                    $updateData['status'] = 'paid';
                    $updateData['paid_date'] = now();
                    $updateData['notes'] = ($payment->notes ? $payment->notes."\n" : '').
                                          'Überweisung erhalten am '.now()->format('d.m.Y H:i');
                    $message = 'Banküberweisung wurde als erhalten markiert.';
                    break;

                case 'invoice':
                    // Rechnung wird als bezahlt markiert
                    $updateData['status'] = 'paid';
                    $updateData['paid_date'] = now();
                    $updateData['notes'] = ($payment->notes ? $payment->notes."\n" : '').
                                          'Rechnung bezahlt am '.now()->format('d.m.Y H:i');
                    $message = 'Rechnung wurde als bezahlt markiert.';
                    break;

                case 'standingorder':
                    // Dauerauftrag wird als bezahlt markiert
                    $updateData['status'] = 'paid';
                    $updateData['paid_date'] = now();
                    $updateData['notes'] = ($payment->notes ? $payment->notes."\n" : '').
                                          'Dauerauftrag eingegangen am '.now()->format('d.m.Y H:i');
                    $message = 'Dauerauftrag wurde als eingegangen markiert.';
                    break;

                case 'sepa_direct_debit':
                    // SEPA-Lastschrift unterstützt derzeit keine manuelle Ausführung
                    DB::rollBack();

                    return redirect()->back()->with('error',
                        'SEPA-Lastschrift kann derzeit nicht manuell ausgeführt werden. '.
                        'Bitte nutzen Sie eine Integration mit einem Zahlungsdienstleister.');

                default:
                    DB::rollBack();

                    return redirect()->back()->with('error',
                        'Unbekannte Zahlungsmethode: '.$paymentMethod);
            }

            // Payment aktualisieren
            $payment->update($updateData);

            // Bei erfolgreicher Zahlung ggf. Membership aktivieren
            if ($updateData['status'] === 'paid' && $payment->membership_id) {
                $membership = $payment->membership;
                if ($membership && $membership->status !== 'active') {
                    if (! $membership->activateMembership()) {
                        $membership->update(['status' => 'active']);
                    }
                }

                $member = $payment->member;
                if ($member && $member->status !== 'active') {
                    $member->update(['status' => 'active']);
                }
            }

            DB::commit();

            return $this->createResponseWithMessage($payment->member, $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fehler beim Ausführen der Standard-Zahlung', [
                'payment_id' => $payment->id,
                'method' => $paymentMethod,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Fehler beim Verarbeiten der Zahlung.');
        }
    }

    protected function createResponseWithMessage(Member $member, string $message)
    {
        return redirect()->back()->with([
            'message' => $message,
            'updated_payments' => true,
            'member_id' => $member->id,
        ]);
    }

    public function executeBatch(Request $request, Member $member)
    {
        $validated = $request->validate([
            'payment_ids' => 'required|array',
            'payment_ids.*' => 'exists:payments,id',
            'payment_method' => 'nullable|string',
        ]);

        $payments = $member->payments()
            ->whereIn('payments.id', $validated['payment_ids'])
            ->whereIn('payments.status', ['pending', 'unknown'])
            ->get();

        if ($payments->isEmpty()) {
            return redirect()->back()->with('error', 'Keine ausstehenden Zahlungen gefunden.');
        }

        // Zahlungsmethode bestimmen
        $paymentMethod = null;

        // 1. Versuche explizit übergebene Methode
        if (isset($validated['payment_method'])) {
            $paymentMethod = $validated['payment_method'];
        } else {
            // 2. Versuche Standard-Zahlungsmethode des Mitglieds
            $defaultPaymentMethod = $member->paymentMethods()
                ->where('status', 'active')
                ->where('is_default', true)
                ->first();

            if ($defaultPaymentMethod) {
                $paymentMethod = $defaultPaymentMethod;
            }
        }

        if (! $paymentMethod) {
            return redirect()->back()->with('error', 'Keine Zahlungsmethode für Batch-Verarbeitung gefunden.');
        }

        // Prüfe ob es eine Standard-Zahlungsmethode ist
        if (! str_starts_with($paymentMethod->type, 'mollie_')) {
            return $this->executeBatchStandardPayments($payments, $paymentMethod->type);
        }

        // Mollie Batch-Verarbeitung
        if (! $this->mollieService->isConfigured($member->gym)) {
            return redirect()->back()->with('error', 'Mollie ist für dieses Gym nicht konfiguriert.');
        }

        if (! $defaultPaymentMethod || ! $defaultPaymentMethod->mollie_customer_id) {
            return redirect()->back()->with('error', 'Keine aktive Mollie-Zahlungsmethode gefunden.');
        }

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($payments as $payment) {
            DB::beginTransaction();

            try {
                // Mollie-Zahlung erstellen
                $this->mollieService->createPaymentWithoutStoring($member, $payment, $paymentMethod);

                DB::commit();
                $successCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                $failedCount++;
                $errors[] = "Zahlung #{$payment->id}: ".$e->getMessage();
                Log::error('Fehler beim Batch-Ausführen der Zahlung', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "$successCount Zahlung(en) erfolgreich verarbeitet.";
        if ($failedCount > 0) {
            $message .= " $failedCount Zahlung(en) fehlgeschlagen.";
        }

        return $this->createResponseWithMessage($member, $message);
    }

    /**
     * Batch-Verarbeitung für Standard-Zahlungsmethoden
     */
    protected function executeBatchStandardPayments($payments, string $paymentMethod)
    {
        // Prüfe ob die Zahlungsmethode unterstützt wird
        if (! in_array($paymentMethod, ['cash', 'banktransfer', 'invoice', 'standingorder'])) {
            if ($paymentMethod === 'sepa_direct_debit') {
                return redirect()->back()->with('error',
                    'SEPA-Lastschrift kann derzeit nicht manuell ausgeführt werden.');
            }

            return redirect()->back()->with('error',
                'Zahlungsmethode wird für Batch-Verarbeitung nicht unterstützt: '.$paymentMethod);
        }

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($payments as $payment) {
            DB::beginTransaction();

            try {
                $updateData = [
                    'payment_method' => $paymentMethod,
                    'status' => 'paid',
                    'paid_date' => now(),
                ];

                // Methoden-spezifische Notiz hinzufügen
                $methodText = match ($paymentMethod) {
                    'cash' => 'Barzahlung erhalten',
                    'banktransfer' => 'Überweisung erhalten',
                    'invoice' => 'Rechnung bezahlt',
                    'standingorder' => 'Dauerauftrag eingegangen',
                    default => 'Zahlung erhalten'
                };

                $updateData['notes'] = ($payment->notes ? $payment->notes."\n" : '').
                                      $methodText.' am '.now()->format('d.m.Y H:i').' (Batch-Verarbeitung)';

                $payment->update($updateData);

                // Membership aktivieren wenn nötig
                if ($payment->membership_id) {
                    $membership = $payment->membership;
                    if ($membership && $membership->status !== 'active') {
                        if (! $membership->activateMembership()) {
                            $membership->update(['status' => 'active']);
                        }
                    }

                    $member = $payment->member;
                    if ($member && $member->status !== 'active') {
                        $member->update(['status' => 'active']);
                    }
                }

                DB::commit();
                $successCount++;

            } catch (\Exception $e) {
                DB::rollBack();
                $failedCount++;
                $errors[] = "Zahlung #{$payment->id}: ".$e->getMessage();
                Log::error('Fehler beim Batch-Ausführen der Standard-Zahlung', [
                    'payment_id' => $payment->id,
                    'method' => $paymentMethod,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $methodText = match ($paymentMethod) {
            'cash' => 'als Barzahlung',
            'banktransfer' => 'als Banküberweisung',
            'invoice' => 'als Rechnungszahlung',
            'standingorder' => 'als Dauerauftrag',
            default => ''
        };

        $message = "$successCount Zahlung(en) erfolgreich verarbeitet.";
        if ($failedCount > 0) {
            $message .= " $failedCount Zahlung(en) fehlgeschlagen.";
        }

        return $this->createResponseWithMessage($member, $message);
    }

    public function invoice(Member $member, Payment $payment)
    {
        // Validierung
        if ($payment->member_id !== $member->id) {
            abort(403, 'Zahlung gehört nicht zu diesem Mitglied.');
        }

        if (! $payment->invoice_path || ! file_exists(storage_path('app/'.$payment->invoice_path))) {
            return redirect()->back()->with('error', 'Rechnung nicht gefunden.');
        }

        // PDF generieren oder vorhandene Rechnung zurückgeben
        return response()->download(storage_path('app/'.$payment->invoice_path));
    }
}
