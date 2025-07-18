<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment as MolliePayment;
use Mollie\Api\Resources\MethodCollection;

class MollieService
{
    protected $client;

    public function __construct()
    {
        $this->client = new MollieApiClient();
    }

    /**
     * Check if Mollie is configured for the gym
     */
    public function isConfigured(Gym $gym): bool
    {
        $config = $gym->mollie_config;

        return $config &&
               isset($config['api_key']) &&
               isset($config['enabled_methods']) &&
               count($config['enabled_methods']) > 0;
    }

    /**
     * Get the Mollie configuration for a gym
     */
    public function getConfig(Gym $gym): array
    {
        return $gym->mollie_config ?? [];
    }

    /**
     * Initialize Mollie client for a gym
     */
    public function initializeClient(Gym $gym): MollieApiClient
    {
        $config = $this->getConfig($gym);

        if (!isset($config['api_key'])) {
            throw new \Exception('Mollie API-Schlüssel nicht konfiguriert');
        }

        $this->client->setApiKey($config['api_key']);

        return $this->client;
    }

    /**
     * Validate OAuth token and check required permissions
     *
     * @param string $oauthToken
     * @return array
     */
    public function validateOAuthToken(string $oauthToken): array
    {
        $requiredPermissions = [
            'payment-links.read',
            'webhooks-subscriptions.read',
            'webhooks-subscriptions.write',
        ];

        try {
            // Initialize Mollie client with OAuth token
            $this->client->setAccessToken($oauthToken);

            // Get permissions for the current token
            $permissions = $this->client->permissions->list();
            $availablePermissions = [];

            foreach ($permissions as $permission) {
                if ($permission->granted)
                    $availablePermissions[] = $permission->id;
            }

            // Check if all required permissions are available
            $missingPermissions = array_diff($requiredPermissions, $availablePermissions);

            if (!empty($missingPermissions)) {
                return [
                    'valid' => false,
                    'message' => 'Fehlende Berechtigungen: ' . implode(', ', $missingPermissions),
                    'missing_permissions' => $missingPermissions,
                    'available_permissions' => $availablePermissions
                ];
            }

            return [
                'valid' => true,
                'message' => 'Token ist gültig und hat alle erforderlichen Berechtigungen',
                'permissions' => $availablePermissions,
            ];

        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            return [
                'valid' => false,
                'message' => 'API-Fehler bei Token-Validierung: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => 'Unerwarteter Fehler bei Token-Validierung: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new payment
     */
    public function createPayment(Gym $gym, array $paymentData): MolliePayment
    {
        $client = $this->initializeClient($gym);
        $config = $this->getConfig($gym);

        // Validate payment details
        $this->validatePaymentData($paymentData);

        // Create Mollie payment
        $molliePayment = $client->payments->create([
            'amount' => [
                'currency' => $paymentData['currency'] ?? 'EUR',
                'value' => number_format($paymentData['amount'], 2, '.', '')
            ],
            'description' => $this->formatDescription($config, $paymentData['description']),
            'redirectUrl' => $paymentData['redirect_url'] ?? $config['redirect_url'],
            'webhookUrl' => $config['webhook_url'],
            'method' => $paymentData['method'] ?? null,
            'metadata' => [
                'gym_id' => $gym->id,
                'user_id' => $paymentData['user_id'] ?? null,
                'member_id' => $paymentData['member_id'] ?? null,
                'invoice_id' => $paymentData['invoice_id'] ?? null,
                'custom_data' => $paymentData['custom_data'] ?? null
            ]
        ]);

        // Save payment in local database
        $this->storePayment($gym, $molliePayment, $paymentData);

        return $molliePayment;
    }

    /**
     * Get a payment from Mollie
     */
    public function getPayment(Gym $gym, string $paymentId): MolliePayment
    {
        $client = $this->initializeClient($gym);

        return $client->payments->get($paymentId);
    }

    /**
     * Process webhook callback
     */
    public function handleWebhook(Gym $gym, string $paymentId)
    {
        try {
            $molliePayment = $this->getPayment($gym, $paymentId);
            $localPayment = Payment::where('gym_id', $gym->id)
                                  ->where('mollie_payment_id', $paymentId)
                                  ->first();

            if (!$localPayment) {
                Log::warning("Lokale Zahlung nicht gefunden für Mollie Payment ID: {$paymentId}");
                return;
            }

            // Update local payment status
            $localPayment->update([
                'status' => $this->mapMollieStatus($molliePayment->status),
                'mollie_status' => $molliePayment->status,
                'paid_at' => $molliePayment->isPaid() ? now() : null,
                'failed_at' => $molliePayment->isFailed() ? now() : null,
                'canceled_at' => $molliePayment->isCanceled() ? now() : null,
                'expired_at' => $molliePayment->isExpired() ? now() : null,
                'webhook_processed_at' => now()
            ]);

            // Perform additional actions based on status
            $this->processPaymentStatusChange($localPayment, $molliePayment);

            Log::info("Webhook erfolgreich verarbeitet für Payment ID: {$paymentId}");

        } catch (\Exception $e) {
            Log::error("Fehler beim Verarbeiten des Webhooks: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform additional actions based on status
     */
    public function getAvailableMethods(Gym $gym): array
    {
        $client = $this->initializeClient($gym);
        $config = $this->getConfig($gym);

        /** @var MethodCollection $methods */
        $methods = $client->methods->allEnabled();
        $enabledMethods = $config['enabled_methods'] ?? [];

        return array_filter($methods->toArray(), function($method) use ($enabledMethods) {
            return in_array($method->id, $enabledMethods);
        });
    }

    /**
     * Validate payment details
     */
    protected function validatePaymentData(array $paymentData): void
    {
        if (!isset($paymentData['amount']) || $paymentData['amount'] <= 0) {
            throw new \InvalidArgumentException('Ungültiger Zahlungsbetrag');
        }

        if (!isset($paymentData['description']) || empty($paymentData['description'])) {
            throw new \InvalidArgumentException('Zahlungsbeschreibung ist erforderlich');
        }

        // Minimum amount for Mollie (usually 0.01 EUR)
        if ($paymentData['amount'] < 0.01) {
            throw new \InvalidArgumentException('Betrag ist zu niedrig');
        }
    }

    /**
     * Format payment description with prefix
     */
    protected function formatDescription(array $config, string $description): string
    {
        $prefix = $config['description_prefix'] ?? '';

        if ($prefix && !str_starts_with($description, $prefix)) {
            return $prefix . ' - ' . $description;
        }

        return $description;
    }

    /**
     * Save payment in local database
     */
    protected function storePayment(Gym $gym, MolliePayment $molliePayment, array $paymentData): Payment
    {
        return Payment::create([
            'gym_id' => $gym->id,
            'membership_id' => null,
            'mollie_payment_id' => $molliePayment->id,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'EUR',
            'description' => $paymentData['description'],
            'status' => $this->mapMollieStatus($molliePayment->status),
            'mollie_status' => $molliePayment->status,
            'payment_method' => $molliePayment->method,
            'checkout_url' => $molliePayment->getCheckoutUrl(),
            'user_id' => $paymentData['user_id'] ?? null,
            'member_id' => $paymentData['member_id'] ?? null,
            'invoice_id' => $paymentData['invoice_id'] ?? null,
            'metadata' => $paymentData['custom_data'] ?? null,
            'due_date' => Carbon::now()->format('Y-m-d'),
            'created_at' => now(),
        ]);
    }

    /**
     * Folder Mollie status on local status
     */
    protected function mapMollieStatus(string $mollieStatus): string
    {
        return match($mollieStatus) {
            'paid' => 'completed',
            'failed' => 'failed',
            'canceled' => 'canceled',
            'expired' => 'expired',
            'pending' => 'pending',
            'open' => 'pending',
            default => 'unknown'
        };
    }

    /**
     * Process status changes
     */
    protected function processPaymentStatusChange(Payment $payment, MolliePayment $molliePayment): void
    {
        switch ($molliePayment->status) {
            case 'paid':
                $this->handleSuccessfulPayment($payment);
                break;
            case 'failed':
            case 'canceled':
            case 'expired':
                $this->handleFailedPayment($payment);
                break;
        }
    }

    /**
     * Handle successful payment
     */
    protected function handleSuccessfulPayment(Payment $payment): void
    {
        // Further actions can be performed here:
        // - Mark invoice as paid
        // - Activate membership
        // - Send notifications

        // Mark invoice as paid
        if ($payment->invoice_id) {
            $invoice = $payment->invoice;
            if ($invoice && $invoice->status !== 'paid') {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => now()
                ]);
            }
        }

        Log::info("Zahlung erfolgreich abgeschlossen: {$payment->mollie_payment_id}");
    }

    /**
     * Handle failed payment
     */
    protected function handleFailedPayment(Payment $payment): void
    {
        // Further actions can be performed here:
        // - Send notifications
        // - Automatic retry logic

        Log::info("Zahlung fehlgeschlagen: {$payment->mollie_payment_id} - Status: {$payment->mollie_status}");
    }

    /**
     * Create refund
     */
    public function createRefund(Gym $gym, string $paymentId, float $amount = null, string $description = null): \Mollie\Api\Resources\Refund
    {
        $client = $this->initializeClient($gym);
        $molliePayment = $this->getPayment($gym, $paymentId);

        $refundData = [];

        if ($amount) {
            $refundData['amount'] = [
                'currency' => $molliePayment->amount->currency,
                'value' => number_format($amount, 2, '.', '')
            ];
        }

        if ($description) {
            $refundData['description'] = $description;
        }

        return $client->payments->get($paymentId)->refunds()->create($refundData);
    }

    /**
     * Get all refunds for a payment
     */
    public function getRefunds(Gym $gym, string $paymentId): \Mollie\Api\Resources\RefundCollection
    {
        $client = $this->initializeClient($gym);

        return $client->payments->get($paymentId)->refunds();
    }

    /**
     * Check if payment is refundable
     */
    public function isRefundable(Gym $gym, string $paymentId): bool
    {
        $molliePayment = $this->getPayment($gym, $paymentId);

        return $molliePayment->isPaid() && $molliePayment->amountRemaining->value > 0;
    }

    /**
     * Get payment statistics for Gym
     */
    public function getPaymentStatistics(Gym $gym, \Carbon\Carbon $from = null, \Carbon\Carbon $to = null): array
    {
        $query = Payment::where('gym_id', $gym->id);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $payments = $query->get();

        return [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'successful_payments' => $payments->where('status', 'completed')->count(),
            'failed_payments' => $payments->where('status', 'failed')->count(),
            'pending_payments' => $payments->where('status', 'pending')->count(),
            'success_rate' => $payments->count() > 0 ?
                round(($payments->where('status', 'completed')->count() / $payments->count()) * 100, 2) : 0
        ];
    }
}
