<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Chargeback;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\WidgetRegistration;
use App\Services\MemberStatusService;
use App\Services\MollieService;
use App\Services\WidgetService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MollieWebhookController extends Controller
{
    public function __construct(
        private WidgetService $widgetService
    ) {}

    /**
     * Mollie Webhook verarbeiten
     */
    public function handle(Request $request)
    {
        try {
            $payload = $request->json()->all();

            // Hook ping
            if (data_get($payload, 'resource') === 'event' &&
                data_get($payload, 'type') === 'hook.ping') {
                return response('OK', 200);
            }

            $paymentId = $request->input('id');

            if (!$paymentId) {
                return response('Payment ID missing', 400);
            }

            // Lokale Payment-Referenz finden
            $localPayment = Payment::where('mollie_payment_id', $paymentId)->first();

            if (!$localPayment) {
                Log::warning('Mollie webhook: Payment reference not found', ['payment_id' => $paymentId]);
                return response('Payment not found', 404);
            }

            $gym = Gym::findOrFail($localPayment->gym_id);
            $mollieService = app(MollieService::class);

            // Aktuellen Payment-Status von Mollie abrufen
            $molliePayment = $mollieService->getPayment($gym, $paymentId);

            // Status aktualisieren
            $oldStatus = $localPayment->status;
            $localPayment->update([
                'status' => $molliePayment->status,
                'paid_date' => $molliePayment->isPaid() ? now() : null
            ]);

            // Prüfen ob es sich um eine 1. Zahlung zur Kreditkarten-Authorisierung handelt
            $isFirstCreditCardPayment = $molliePayment->sequenceType === 'first'
                && $molliePayment->method === 'creditcard';

            // Wenn 1. Kreditkarten-Authorisierung erfolgreich war
            if ($isFirstCreditCardPayment && $molliePayment->isPaid() && $oldStatus !== 'paid') {
                $this->handleFirstCreditCardPayment($gym, $localPayment, $molliePayment, $paymentId);
                return response('OK', 200);
            }

            // Wenn Payment neu bezahlt wurde (reguläre Zahlung, keine Authorisierung)
            if ($molliePayment->isPaid() && $oldStatus !== 'paid') {
                $this->handlePaymentPaid($gym, $localPayment, $molliePayment, $mollieService, $paymentId);
            }

            // Wenn Payment fehlgeschlagen und Mitglied aktiv ist
            if ($molliePayment->isFailed() && $oldStatus !== 'failed') {
                $this->handlePaymentFailed($gym, $localPayment, $paymentId);
            }

            // Refunds verarbeiten
            $this->processRefunds($gym, $localPayment, $molliePayment, $mollieService);

            // Chargebacks verarbeiten
            $this->processChargebacks($gym, $localPayment, $molliePayment, $mollieService);

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Mollie webhook processing failed', [
                'payment_id' => $request->input('id'),
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle first credit card authorization payment
     */
    private function handleFirstCreditCardPayment(Gym $gym, Payment $localPayment, $molliePayment, string $paymentId): void
    {
        $member = $localPayment->member;
        $membership = $localPayment->membership;

        // Member und Membership aktivieren
        $member->update(['status' => 'active']);
        if ($membership) {
            if (!$membership->activateMembership()) {
                $membership->update(['status' => 'active']);
            }
        }

        // PaymentMethod mit Mandat-Daten aktivieren
        $paymentMethod = PaymentMethod::where('member_id', $member->id)
            ->where('type', 'mollie_creditcard')
            ->where('status', 'pending')
            ->first();

        if ($paymentMethod) {
            $paymentMethod->update([
                'status' => 'active',
                'mollie_customer_id' => $molliePayment->customerId,
                'mollie_mandate_id' => $molliePayment->mandateId,
            ]);
            $paymentMethod->activateSepaMandate();
        }

        // Widget-Registrierung abschließen
        WidgetRegistration::where('gym_id', $gym->id)
            ->where('payment_data', 'like', '%' . $molliePayment->id . '%')
            ->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

        // Analytics
        $this->widgetService->trackEvent($gym, 'mollie_webhook_creditcard_authorized', 'payment_webhook', [
            'member_id' => $member->id,
            'membership_id' => $membership?->id,
            'payment_method' => 'creditcard',
            'mollie_payment_id' => $paymentId,
            'mollie_mandate_id' => $molliePayment->mandateId
        ]);

        Log::info('Mollie webhook: Credit card authorization completed', [
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'payment_id' => $paymentId,
            'mandate_id' => $molliePayment->mandateId
        ]);
    }

    /**
     * Handle regular payment paid
     */
    private function handlePaymentPaid(Gym $gym, Payment $localPayment, $molliePayment, MollieService $mollieService, string $paymentId): void
    {
        $member = $localPayment->member;
        $membership = $localPayment->membership;

        // Member und Membership aktivieren
        $member->update(['status' => 'active']);
        if (!$membership->activateMembership()) {
            $membership->update(['status' => 'active']);
        }

        // Widget-Registrierung abschließen
        WidgetRegistration::where('gym_id', $gym->id)
            ->where('payment_data', 'like', '%' . $molliePayment->id . '%')
            ->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

        // PaymentMethod aktualisieren
        $mollieService->activateMolliePaymentMethod($gym, $member->id, $localPayment->payment_method);

        // Analytics
        $this->widgetService->trackEvent($gym, 'mollie_webhook_paid', 'payment_webhook', [
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'payment_method' => $localPayment->method,
            'amount' => $localPayment->amount,
            'mollie_payment_id' => $paymentId
        ]);

        Log::info('Mollie webhook: Payment completed', [
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'payment_id' => $paymentId
        ]);
    }

    /**
     * Handle payment failed
     */
    private function handlePaymentFailed(Gym $gym, Payment $localPayment, string $paymentId): void
    {
        $member = $localPayment->member;

        if ($member && $member->status === 'active') {
            $memberStatusService = app(MemberStatusService::class);
            $memberStatusService->handlePaymentFailed($member, [
                'mollie_payment_id' => $paymentId,
                'amount' => $localPayment->amount,
                'payment_method' => $localPayment->payment_method,
                'gym_id' => $gym->id
            ]);

            // Analytics
            $this->widgetService->trackEvent($gym, 'mollie_webhook_failed', 'payment_webhook', [
                'member_id' => $member->id,
                'payment_method' => $localPayment->payment_method,
                'amount' => $localPayment->amount,
                'mollie_payment_id' => $paymentId
            ]);

            Log::warning('Mollie webhook: Payment failed, member set to overdue', [
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'payment_id' => $paymentId
            ]);
        }
    }

    /**
     * Process refunds from Mollie webhook
     */
    private function processRefunds(Gym $gym, Payment $localPayment, $molliePayment, MollieService $mollieService): void
    {
        try {
            $mollieRefunds = $mollieService->getRefunds($gym, $molliePayment->id);

            foreach ($mollieRefunds as $mollieRefund) {
                // Prüfen ob Refund bereits existiert
                $existingRefund = Refund::where('mollie_refund_id', $mollieRefund->id)->first();

                if ($existingRefund) {
                    // Status aktualisieren falls geändert
                    if ($existingRefund->mollie_status !== $mollieRefund->status) {
                        $existingRefund->update([
                            'status' => $this->mapMollieRefundStatus($mollieRefund->status),
                            'mollie_status' => $mollieRefund->status,
                        ]);
                    }
                    continue;
                }

                // Neuen Refund anlegen
                $refund = Refund::create([
                    'payment_id' => $localPayment->id,
                    'mollie_refund_id' => $mollieRefund->id,
                    'amount' => $mollieRefund->amount->value,
                    'currency' => $mollieRefund->amount->currency,
                    'description' => $mollieRefund->description,
                    'status' => $this->mapMollieRefundStatus($mollieRefund->status),
                    'mollie_status' => $mollieRefund->status,
                ]);

                // Refund-Payment als eigene Zahlung anlegen (verknüpft via transaction_id)
                $refundPayment = Payment::create([
                    'gym_id' => $localPayment->gym_id,
                    'membership_id' => $localPayment->membership_id,
                    'member_id' => $localPayment->member_id,
                    'mollie_payment_id' => $mollieRefund->id,
                    'amount' => -abs($mollieRefund->amount->value),
                    'currency' => $mollieRefund->amount->currency,
                    'description' => 'Rückerstattung: ' . ($mollieRefund->description ?? $localPayment->description),
                    'status' => $this->mapMollieRefundStatus($mollieRefund->status) === 'refunded' ? 'refunded' : 'pending',
                    'mollie_status' => $mollieRefund->status,
                    'payment_method' => $localPayment->payment_method,
                    'due_date' => now()->format('Y-m-d'),
                    'paid_date' => $mollieRefund->status === 'refunded' ? now() : null,
                ]);

                // Original-Payment-Status aktualisieren
                $this->updatePaymentRefundStatus($localPayment, $molliePayment);

                Log::info('Mollie webhook: Refund processed', [
                    'gym_id' => $gym->id,
                    'payment_id' => $localPayment->id,
                    'refund_id' => $refund->id,
                    'refund_payment_id' => $refundPayment->id,
                    'mollie_refund_id' => $mollieRefund->id,
                    'amount' => $mollieRefund->amount->value,
                ]);

                // Analytics
                $this->widgetService->trackEvent($gym, 'mollie_webhook_refund', 'payment_webhook', [
                    'payment_id' => $localPayment->id,
                    'refund_id' => $refund->id,
                    'amount' => $mollieRefund->amount->value,
                    'mollie_refund_id' => $mollieRefund->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Mollie webhook: Failed to process refunds', [
                'payment_id' => $localPayment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process chargebacks from Mollie webhook
     */
    private function processChargebacks(Gym $gym, Payment $localPayment, $molliePayment, MollieService $mollieService): void
    {
        try {
            $mollieChargebacks = $mollieService->getChargebacks($gym, $molliePayment->id);

            foreach ($mollieChargebacks as $mollieChargeback) {
                // Prüfen ob Chargeback bereits existiert
                $existingChargeback = Chargeback::where('mollie_chargeback_id', $mollieChargeback->id)->first();

                if ($existingChargeback) {
                    continue;
                }

                // Neuen Chargeback anlegen
                $chargeback = Chargeback::create([
                    'payment_id' => $localPayment->id,
                    'mollie_chargeback_id' => $mollieChargeback->id,
                    'amount' => $mollieChargeback->amount->value,
                    'currency' => $mollieChargeback->amount->currency,
                    'status' => 'received',
                    'mollie_status' => 'chargeback',
                    'chargeback_date' => $mollieChargeback->createdAt ? Carbon::parse($mollieChargeback->createdAt) : now(),
                ]);

                // Chargeback-Payment als eigene Zahlung anlegen (verknüpft via transaction_id)
                $chargebackPayment = Payment::create([
                    'gym_id' => $localPayment->gym_id,
                    'membership_id' => $localPayment->membership_id,
                    'member_id' => $localPayment->member_id,
                    'mollie_payment_id' => $mollieChargeback->id,
                    'amount' => -abs($mollieChargeback->amount->value),
                    'currency' => $mollieChargeback->amount->currency,
                    'description' => 'Rückbuchung (Chargeback): ' . $localPayment->description,
                    'status' => 'chargeback',
                    'mollie_status' => 'chargeback',
                    'payment_method' => $localPayment->payment_method,
                    'due_date' => now()->format('Y-m-d'),
                    'paid_date' => now(),
                ]);

                // Mitglied auf overdue setzen bei Chargeback
                $member = $localPayment->member;
                if ($member && $member->status === 'active') {
                    $memberStatusService = app(MemberStatusService::class);
                    $memberStatusService->handlePaymentFailed($member, [
                        'mollie_payment_id' => $molliePayment->id,
                        'amount' => $localPayment->amount,
                        'payment_method' => $localPayment->payment_method,
                        'gym_id' => $gym->id,
                        'reason' => 'chargeback',
                    ]);
                }

                Log::warning('Mollie webhook: Chargeback received', [
                    'gym_id' => $gym->id,
                    'payment_id' => $localPayment->id,
                    'chargeback_id' => $chargeback->id,
                    'chargeback_payment_id' => $chargebackPayment->id,
                    'mollie_chargeback_id' => $mollieChargeback->id,
                    'amount' => $mollieChargeback->amount->value,
                    'member_id' => $member?->id,
                ]);

                // Analytics
                $this->widgetService->trackEvent($gym, 'mollie_webhook_chargeback', 'payment_webhook', [
                    'payment_id' => $localPayment->id,
                    'chargeback_id' => $chargeback->id,
                    'amount' => $mollieChargeback->amount->value,
                    'mollie_chargeback_id' => $mollieChargeback->id,
                    'member_id' => $member?->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Mollie webhook: Failed to process chargebacks', [
                'payment_id' => $localPayment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map Mollie refund status to local status
     */
    private function mapMollieRefundStatus(string $mollieStatus): string
    {
        return match($mollieStatus) {
            'queued' => 'pending',
            'pending' => 'processing',
            'processing' => 'processing',
            'refunded' => 'refunded',
            'failed' => 'failed',
            default => 'pending'
        };
    }

    /**
     * Update payment status based on refund amounts
     */
    private function updatePaymentRefundStatus(Payment $payment, $molliePayment): void
    {
        $amountRefunded = floatval($molliePayment->amountRefunded->value ?? 0);
        $amountOriginal = floatval($molliePayment->amount->value);

        if ($amountRefunded >= $amountOriginal) {
            $payment->update(['status' => 'refunded']);
        } elseif ($amountRefunded > 0) {
            $payment->update(['status' => 'partially_refunded']);
        }
    }
}
