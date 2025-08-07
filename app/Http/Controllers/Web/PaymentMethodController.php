<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Member $member)
    {
        // Ensure user can only access payment methods from their gym
        $this->authorize('create', PaymentMethod::class);

        $validated = $request->validate([
            'type' => 'required|in:sepa_direct_debit,creditcard,banktransfer,cash,invoice',
            'status' => 'required|in:active,pending',
            'is_default' => 'boolean',
            // SEPA
            'iban' => 'required_if:type,sepa_direct_debit|nullable|string', // TODO: Benutze Funktion validateIban aus WidgetService
            'account_holder' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'sepa_mandate_acknowledged' => 'boolean',
            'requires_mandate' => 'boolean',
            // Credit Card
            'last_four' => 'required_if:type,creditcard|nullable|digits:4',
            'cardholder_name' => 'required_if:type,creditcard|nullable|string',
            'expiry_date' => 'required_if:type,creditcard|nullable|date',
            // Bank Transfer
            'notes' => 'nullable|string',
        ]);

        // Wenn als Standard gesetzt, alle anderen deaktivieren
        if ($validated['is_default']) {
            $member->paymentMethods()->update(['is_default' => false]);
        }

        // SEPA-spezifische Logik
        if ($validated['type'] === 'sepa_direct_debit') {
            $paymentMethod = PaymentMethod::createSepaPaymentMethod(
                $member,
                $validated['sepa_mandate_acknowledged'] ?? false
            );

            $paymentMethod->update([
                'iban' => $validated['iban'],
                'bank_name' => $validated['bank_name'] ?? null,
                'is_default' => $validated['is_default'] ?? false,
            ]);
        } else {
            $paymentMethod = $member->paymentMethods()->create($validated);
        }

        return back()->with('success', 'Zahlungsmethode erfolgreich hinzugefügt.');
    }

    public function setAsDefault(Member $member, PaymentMethod $paymentMethod)
    {
        // Ensure user can only modify payment methods from their gym
        $this->authorize('update', $paymentMethod);

        // Alle anderen als nicht-Standard setzen
        $member->paymentMethods()->update(['is_default' => false]);

        // Diese als Standard setzen
        $paymentMethod->update(['is_default' => true]);

        return back()->with('success', 'Zahlungsmethode als Standard gesetzt.');
    }

    public function update(Request $request, Member $member, PaymentMethod $paymentMethod)
    {
        // Ensure user can only modify payment methods from their gym
        $this->authorize('update', $paymentMethod);

        $validated = $request->validate([
            'status' => 'required|in:active,pending,expired,failed',
            'is_default' => 'boolean',
            // SEPA fields
            'iban' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'sepa_mandate_status' => 'nullable|in:pending,signed,active,revoked,expired',
            'sepa_mandate_reference' => 'nullable|string',
            // Credit card fields
            'last_four' => 'nullable|digits:4',
            'cardholder_name' => 'nullable|string',
            'expiry_date' => 'nullable|date',
        ]);

        if ($validated['is_default']) {
            $member->paymentMethods()->update(['is_default' => false]);
        }

        // Typ kann nicht geändert werden
        unset($validated['type']);

        $paymentMethod->update($validated);

        return back()->with('success', 'Zahlungsmethode aktualisiert.');
    }

    public function deactivate(Member $member, PaymentMethod $paymentMethod)
    {
        // Ensure user can only modify payment methods from their gym
        $this->authorize('update', $paymentMethod);

        $paymentMethod->update(['status' => 'expired']);

        return back()->with('success', 'Zahlungsmethode deaktiviert.');
    }
}
