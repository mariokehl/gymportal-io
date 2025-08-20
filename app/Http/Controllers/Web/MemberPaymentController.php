<?php
// app/Http/Controllers/MemberPaymentController.php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Payment;
use App\Services\MollieService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberPaymentController extends Controller
{
    protected MollieService $mollieService;

    public function __construct(MollieService $mollieService)
    {
        $this->mollieService = $mollieService;
    }

    public function store(Request $request, Member $member)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'due_date' => 'nullable|date',
            'paid_date' => 'nullable|date',
            'payment_method' => 'nullable|string',
            'status' => 'required|in:pending,paid',
            'notes' => 'nullable|string'
        ]);

        $payment = $member->payments()->create([
            ...$validated,
            'gym_id' => $member->gym_id,
            'membership_id' => $member->memberships()->first()?->id,
            'member_id' => $member->id,
        ]);

        return redirect()->back()->with('message', 'Zahlung wurde hinzugefügt.');
    }

    public function execute(Request $request, Member $member, Payment $payment)
    {
        // Validierung
        if ($payment->member_id !== $member->id) {
            return redirect()->back()->with('error', 'Zahlung gehört nicht zu diesem Mitglied.');
        }

        if (!in_array($payment->status, ['pending', 'unknown'])) {
            return redirect()->back()->with('error', 'Nur ausstehende Zahlungen können ausgeführt werden.');
        }

        DB::beginTransaction();

        try {
            // Zahlungsmethode des Mitglieds abrufen
            $paymentMethod = $member->paymentMethods()
                ->where('status', 'active')
                ->where('type', $payment->payment_method)
                ->first();

            // Prüfe ob es die Zahlungsmethode überhaupt gibt
            if (!$paymentMethod) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Keine Zahlungsart für die Zahlungsmethode hinterlegt.');
            }

            // Prüfe ob es eine Standard-Zahlungsmethode ist (nicht Mollie)
            if (!str_starts_with($paymentMethod->type, 'mollie_')) {
                return $this->executeStandardPayment($payment, $paymentMethod->type);
            }

            // Ab hier: Mollie-Zahlungsmethoden
            // Prüfen ob Mollie konfiguriert ist
            if (!$this->mollieService->isConfigured($member->gym)) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Mollie ist für dieses Gym nicht konfiguriert.');
            }

            // Mollie Customer ID prüfen
            if (!$paymentMethod->mollie_customer_id) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Kein Mollie-Kunde für dieses Mitglied gefunden.');
            }

            // Mollie-Zahlung erstellen
            $this->mollieService->createPaymentWithoutStoring($member, $payment, $paymentMethod);

            DB::commit();

            $this->createResponseWithMessage($member, 'Zahlung wird über Mollie ausgeführt.');

        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            DB::rollBack();
            Log::error('Mollie API Fehler beim Ausführen der Zahlung', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'Mollie-Fehler: ' . $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fehler beim Ausführen der Zahlung', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
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
                    $updateData['notes'] = ($payment->notes ? $payment->notes . "\n" : '') .
                                          'Barzahlung erhalten am ' . now()->format('d.m.Y H:i');
                    $message = 'Barzahlung wurde als erhalten markiert.';
                    break;

                case 'banktransfer':
                    // Banküberweisung wird als bezahlt markiert (manuell überprüft)
                    $updateData['status'] = 'paid';
                    $updateData['paid_date'] = now();
                    $updateData['notes'] = ($payment->notes ? $payment->notes . "\n" : '') .
                                          'Überweisung erhalten am ' . now()->format('d.m.Y H:i');
                    $message = 'Banküberweisung wurde als erhalten markiert.';
                    break;

                case 'invoice':
                    // Rechnung wird als bezahlt markiert
                    $updateData['status'] = 'paid';
                    $updateData['paid_date'] = now();
                    $updateData['notes'] = ($payment->notes ? $payment->notes . "\n" : '') .
                                          'Rechnung bezahlt am ' . now()->format('d.m.Y H:i');
                    $message = 'Rechnung wurde als bezahlt markiert.';
                    break;

                case 'standingorder':
                    // Dauerauftrag wird als bezahlt markiert
                    $updateData['status'] = 'paid';
                    $updateData['paid_date'] = now();
                    $updateData['notes'] = ($payment->notes ? $payment->notes . "\n" : '') .
                                          'Dauerauftrag eingegangen am ' . now()->format('d.m.Y H:i');
                    $message = 'Dauerauftrag wurde als eingegangen markiert.';
                    break;

                case 'sepa_direct_debit':
                    // SEPA-Lastschrift unterstützt derzeit keine manuelle Ausführung
                    DB::rollBack();
                    return redirect()->back()->with('error',
                        'SEPA-Lastschrift kann derzeit nicht manuell ausgeführt werden. ' .
                        'Bitte nutzen Sie eine Integration mit einem Zahlungsdienstleister.');

                default:
                    DB::rollBack();
                    return redirect()->back()->with('error',
                        'Unbekannte Zahlungsmethode: ' . $paymentMethod);
            }

            // Payment aktualisieren
            $payment->update($updateData);

            // Bei erfolgreicher Zahlung ggf. Membership aktivieren
            if ($updateData['status'] === 'paid' && $payment->membership_id) {
                $membership = $payment->membership;
                if ($membership && $membership->status !== 'active') {
                    $membership->update(['status' => 'active']);
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
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'Fehler beim Verarbeiten der Zahlung.');
        }
    }

    protected function createResponseWithMessage(Member $member, string $message)
    {
        return redirect()->back()->with([
            'message' => $message,
            'updated_payments' => true,
            'member_id' => $member->id
        ]);
    }

    public function executeBatch(Request $request, Member $member)
    {
        $validated = $request->validate([
            'payment_ids' => 'required|array',
            'payment_ids.*' => 'exists:payments,id',
            'payment_method' => 'nullable|string'
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

        if (!$paymentMethod) {
            return redirect()->back()->with('error', 'Keine Zahlungsmethode für Batch-Verarbeitung gefunden.');
        }

        // Prüfe ob es eine Standard-Zahlungsmethode ist
        if (!str_starts_with($paymentMethod->type, 'mollie_')) {
            return $this->executeBatchStandardPayments($payments, $paymentMethod->type);
        }

        // Mollie Batch-Verarbeitung
        if (!$this->mollieService->isConfigured($member->gym)) {
            return redirect()->back()->with('error', 'Mollie ist für dieses Gym nicht konfiguriert.');
        }

        if (!$defaultPaymentMethod || !$defaultPaymentMethod->mollie_customer_id) {
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
                $errors[] = "Zahlung #{$payment->id}: " . $e->getMessage();
                Log::error('Fehler beim Batch-Ausführen der Zahlung', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
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
        if (!in_array($paymentMethod, ['cash', 'banktransfer', 'invoice', 'standingorder'])) {
            if ($paymentMethod === 'sepa_direct_debit') {
                return redirect()->back()->with('error',
                    'SEPA-Lastschrift kann derzeit nicht manuell ausgeführt werden.');
            }
            return redirect()->back()->with('error',
                'Zahlungsmethode wird für Batch-Verarbeitung nicht unterstützt: ' . $paymentMethod);
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
                $methodText = match($paymentMethod) {
                    'cash' => 'Barzahlung erhalten',
                    'banktransfer' => 'Überweisung erhalten',
                    'invoice' => 'Rechnung bezahlt',
                    'standingorder' => 'Dauerauftrag eingegangen',
                    default => 'Zahlung erhalten'
                };

                $updateData['notes'] = ($payment->notes ? $payment->notes . "\n" : '') .
                                      $methodText . ' am ' . now()->format('d.m.Y H:i') . ' (Batch-Verarbeitung)';

                $payment->update($updateData);

                // Membership aktivieren wenn nötig
                if ($payment->membership_id) {
                    $membership = $payment->membership;
                    if ($membership && $membership->status !== 'active') {
                        $membership->update(['status' => 'active']);
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
                $errors[] = "Zahlung #{$payment->id}: " . $e->getMessage();
                Log::error('Fehler beim Batch-Ausführen der Standard-Zahlung', [
                    'payment_id' => $payment->id,
                    'method' => $paymentMethod,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $methodText = match($paymentMethod) {
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

        if (!$payment->invoice_path || !file_exists(storage_path('app/' . $payment->invoice_path))) {
            return redirect()->back()->with('error', 'Rechnung nicht gefunden.');
        }

        // PDF generieren oder vorhandene Rechnung zurückgeben
        return response()->download(storage_path('app/' . $payment->invoice_path));
    }
}
