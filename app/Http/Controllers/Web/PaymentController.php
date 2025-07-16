<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function show(Payment $payment): JsonResponse
    {
        // Ensure user can only access payments from their gym
        $this->authorize('view', $payment);

        $payment->load(['membership.member', 'invoice']);

        return response()->json([
            'payment' => $payment
        ]);
    }

    public function markAsPaid(Payment $payment): RedirectResponse
    {
        // Ensure user can only modify payments from their gym
        $this->authorize('update', $payment);

        if ($payment->status !== 'pending') {
            return redirect()->back()->with('error', 'Nur ausstehende Zahlungen können als bezahlt markiert werden.');
        }

        $payment->update([
            'status' => 'paid',
            'paid_date' => now(),
            'paid_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Zahlung wurde als bezahlt markiert.');
    }

    public function markAsFailed(Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        if ($payment->status !== 'pending') {
            return redirect()->back()->with('error', 'Nur ausstehende Zahlungen können als fehlgeschlagen markiert werden.');
        }

        $payment->update([
            'status' => 'failed',
            'failed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Zahlung wurde als fehlgeschlagen markiert.');
    }

    public function refund(Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);

        if ($payment->status !== 'paid') {
            return redirect()->back()->with('error', 'Nur bezahlte Zahlungen können erstattet werden.');
        }

        $payment->update([
            'status' => 'refunded',
        ]);

        return redirect()->back()->with('success', 'Zahlung wurde erstattet.');
    }
}
