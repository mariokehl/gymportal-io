<?php
// app/Http/Controllers/MemberPaymentController.php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Http\Request;

class MemberPaymentController extends Controller
{
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

        return redirect()->back()->with('success', 'Zahlung wurde hinzugefügt.');
    }

    public function execute(Member $member, Payment $payment)
    {
        // Zahlung über Payment Provider ausführen
        // z.B. Mollie, Stripe, etc.

        $payment->update([
            'status' => 'processing',
            // weitere Updates...
        ]);

        return redirect()->back()->with('success', 'Zahlung wird ausgeführt.');
    }

    public function executeBatch(Request $request, Member $member)
    {
        $paymentIds = $request->validate([
            'payment_ids' => 'required|array',
            'payment_ids.*' => 'exists:payments,id'
        ])['payment_ids'];

        $payments = $member->payments()
            ->whereIn('id', $paymentIds)
            ->where('status', 'pending')
            ->get();

        foreach ($payments as $payment) {
            // Zahlung ausführen
        }

        return redirect()->back()->with('success', count($payments) . ' Zahlungen werden ausgeführt.');
    }

    public function invoice(Member $member, Payment $payment)
    {
        // PDF generieren oder vorhandene Rechnung zurückgeben
        return response()->download($payment->invoice_path);
    }
}
