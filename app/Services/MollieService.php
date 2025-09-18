<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\Payment as MolliePayment;
use Mollie\Api\Resources\MethodCollection;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Resources\RefundCollection;
use Mollie\Api\Types\MandateMethod;

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
            throw new Exception('Mollie API-Schlüssel nicht konfiguriert');
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
            'webhooks.read',
            'webhooks.write',
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

        } catch (ApiException $e) {
            return [
                'valid' => false,
                'message' => 'API-Fehler bei Token-Validierung: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Unerwarteter Fehler bei Token-Validierung: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new customer
     */
    public function createCustomer(Gym $gym, string $name, string $email): Customer
    {
        $client = $this->initializeClient($gym);

        $customer = $client->customers->create([
            'name' => $name,
            'email' => $email,
        ]);

        return $customer;
    }

    /**
     * Create a new first payment
     */
    public function createFirstPayment(Gym $gym, string $customerId, array $paymentData): MolliePayment
    {
        // First and recurring payment is only possible for certain types.
        // see https://help.mollie.com/hc/en-us/articles/115000470109-What-is-Mollie-Recurring
        if ($gym->getMollieMandateType(str_replace('mollie_', '', $paymentData['method']))) {
            $paymentData['sequenceType'] = 'first';
        }
        $paymentData['customerId'] = $customerId;

        return $this->createPayment($gym, $paymentData);
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

        // Basis-Parameter für Mollie Payment
        $mollieParams = [
            'amount' => [
                'currency' => $paymentData['currency'] ?? 'EUR',
                'value' => number_format($paymentData['amount'], 2, '.', '')
            ],
            'description' => $this->formatDescription($config, $paymentData['description']),
            'redirectUrl' => $paymentData['redirectUrl'] ?? $config['redirect_url'],
            'webhookUrl' => $config['webhook_url'] ?? '',
            'method' => str_starts_with($paymentData['method'], 'mollie_')
                ? substr($paymentData['method'], 7)
                : $paymentData['method'],
            'metadata' => $paymentData['metadata'] ?? []
        ];

        // Optionale Parameter hinzufügen (für wiederkehrende Zahlungen)
        if (isset($paymentData['customerId'])) {
            $mollieParams['customerId'] = $paymentData['customerId'];
        }

        if (isset($paymentData['sequenceType'])) {
            $mollieParams['sequenceType'] = $paymentData['sequenceType'];
        }

        // Create Mollie payment
        $molliePayment = $client->payments->create($mollieParams);

        // Save payment in local database
        $this->storePayment($gym, $molliePayment, $paymentData);

        return $molliePayment;
    }

    /**
     * Create a new payment without storing it in the database
     * Use this when you want to update an existing Payment model
     */
    public function createPaymentWithoutStoring(Member $member, Payment $payment, PaymentMethod $paymentMethod): MolliePayment
    {
        $client = $this->initializeClient($member->gym);
        $config = $this->getConfig($member->gym);

        // Setup payment details
        $paymentData = [
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'EUR',
            'description' => $this->formatDescription($config, $payment->description),
            'method' => $paymentMethod->type,
            'customerId' => $paymentMethod->mollie_customer_id,
            'sequenceType' => 'recurring', // (also for one-off payments with an existing mandate)
            'mandateId' => $paymentMethod->mollie_mandate_id,
            'metadata' => [
                'payment_id' => $payment->id,
                'member_id' => $member->id,
                'membership_id' => $payment->membership_id,
                'gym_id' => $member->gym_id
            ]
        ];

        // Validate payment details
        $this->validatePaymentData($paymentData);

        // Create Mollie payment
        $molliePayment = $client->payments->create($this->getMollieParamsBy($paymentData, $config));

        // Update payment in local database
        $this->updatePayment($molliePayment, $payment, $paymentMethod);

        return $molliePayment;
    }

    /**
     * Cancel payment: Depending on the payment method, you may be able to cancel a payment
     * for a certain amount of time — usually until the next business day or as long as the payment status is open.
     */
    public function cancelPayment(Member $member, Payment $payment): ?MolliePayment
    {
        $client = $this->initializeClient($member->gym);

        /** @var MolliePayment $molliePayment */
        $molliePayment = $client->payments->get($payment->mollie_payment_id);

        // Prüfen ob die Zahlung bei Mollie abgebrochen werden kann
        if ($molliePayment->isCancelable) {
            return $client->payments->delete($payment->mollie_payment_id);
        }

        return null;
    }

    /**
     * @param array $paymentData
     * @param array $config
     * @return array
     */
    private function getMollieParamsBy(array $paymentData, array $config): array
    {
        $mollieParams = [
            'amount' => [
                'currency' => $paymentData['currency'] ?? 'EUR',
                'value' => number_format($paymentData['amount'], 2, '.', '')
            ],
            'description' => $this->formatDescription($config, $paymentData['description']),
            'redirectUrl' => $paymentData['redirectUrl'] ?? $config['redirect_url'],
            'webhookUrl' => $paymentData['webhookUrl'] ?? $config['webhook_url'],
            'method' => str_starts_with($paymentData['method'], 'mollie_')
                ? substr($paymentData['method'], 7)
                : $paymentData['method'],
            'metadata' => $paymentData['metadata'] ?? []
        ];

        // Optionale Parameter hinzufügen (für wiederkehrende Zahlungen)
        if (isset($paymentData['customerId'])) {
            $mollieParams['customerId'] = $paymentData['customerId'];
        }

        if (isset($paymentData['sequenceType'])) {
            $mollieParams['sequenceType'] = $paymentData['sequenceType'];
        }

        if (isset($paymentData['mandateId'])) {
            $mollieParams['mandateId'] = $paymentData['mandateId'];
        }

        return $mollieParams;
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
     * Get a mandate from Mollie
     *
     * @param Gym $gym
     * @param string $customerId
     * @return Mandate|null
     */
    public function getMandate(Gym $gym, string $customerId): ?Mandate
    {
        $client = $this->initializeClient($gym);

        $mandates = $client->customers->get($customerId)->mandates();
        $mandate = collect($mandates)->firstWhere('status', 'valid');

        return $mandate;
    }

    /**
     * Create a mandate for a specific customer
     *
     * @param Gym $gym
     * @param string $customerId Provide the ID of the related customer.
     * @param PaymentMethod $method Payment method of the mandate. SEPA Direct Debit and PayPal mandates can be created directly. Possible values: creditcard directdebit paypal
     * @param string $consumerName The customer's name.
     * @return void
     */
    public function createMandate(Gym $gym, string $customerId, PaymentMethod $paymentMethod, string $consumerName): Mandate
    {
        $client = $this->initializeClient($gym);
        $mandate = $client->customers->get($customerId)->createMandate([
            'method' => MandateMethod::getForFirstPaymentMethod(str_replace('mollie_', '', $paymentMethod->type)),
            'consumerName' => $paymentMethod->account_holder ?? $consumerName,
            'consumerAccount' => $paymentMethod->iban,
            'consumerBic' => '',
            'signatureDate' => $paymentMethod->sepa_mandate_signed_at?->toDateString() ?? Carbon::now()->toDateString(),
            'mandateReference' => $paymentMethod->sepa_mandate_reference,
        ]);

        return $mandate;
    }

    /**
     * Process webhook callback
     *
     * @deprecated Diese Funktion wird aktuell nicht verwendet.
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
                'paid_date' => $molliePayment->isPaid() ? now() : null,
                'failed_at' => $molliePayment->isFailed() ? now() : null,
                'canceled_at' => $molliePayment->isCanceled() ? now() : null,
                'expired_at' => $molliePayment->isExpired() ? now() : null,
                'webhook_processed_at' => now()
            ]);

            // Perform additional actions based on status
            $this->processPaymentStatusChange($localPayment, $molliePayment);

            Log::info("Webhook erfolgreich verarbeitet für Payment ID: {$paymentId}");

        } catch (Exception $e) {
            Log::error("Fehler beim Verarbeiten des Webhooks: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create webhook in Mollie
     *
     * @param string $oauthToken
     * @param string $webhookUrl
     * @param boolean $testMode
     * @return string The webhooks idenitifier
     * @throws Exception If webhook could not be created
     */
    public static function createWebhook(string $oauthToken, string $webhookUrl, bool $testMode): string
    {
        // TODO: Check if the webhook URL has changed. If so, update it.
        // ...

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $oauthToken,
            'Content-Type' => 'application/json'
        ])->post('https://api.mollie.com/v2/webhooks', [
            'url' => !app()->environment('local') ? $webhookUrl : request()->getSchemeAndHttpHost() . '/api/v1/public/mollie/webhook',
            'name' => 'Webhook #1 (autogenerated by gymportal.io)' . ($testMode ? ' TEST' : null),
            'eventTypes' => 'payment-link.paid',
            'testmode' => $testMode,
        ]);

        if ($response->successful()) {
            return $response->json()['id'];
        }

        throw new Exception('Webhook konnte nicht erstellt werden: ' . $response->body());
    }

    /**
     * Delete webhook in Mollie
     *
     * @param array $config
     * @return void
     */
    public static function deleteWebhookIfAny(array $config = []): void
    {
        // Delete webhook in Mollie if present
        if ($config && isset($config['webhook_id'])) {
            try {
                $httpRequest = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $config['oauth_token']
                ]);

                // Add testmode to body if configured
                if (isset($config['test_mode']) && $config['test_mode']) {
                    $httpRequest = $httpRequest->withBody(
                        json_encode(['testmode' => true]),
                        'application/json'
                    );
                }

                $response = $httpRequest->delete("https://api.mollie.com/v2/webhooks/{$config['webhook_id']}");
            } catch (Exception $e) {
                // Webhook deletion failed, but we're continuing anyway
            }
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
        if (!isset($paymentData['description']) || empty($paymentData['description'])) {
            throw new \InvalidArgumentException('Zahlungsbeschreibung ist erforderlich');
        }

        // For credit card and PayPal payments, you can create a payment with a zero amount.
        // No money will then be debited from the card or account when doing the first payment.
        if (
            strpos($paymentData['method'], 'creditcard') &&
            isset($paymentData['sequenceType']) && $paymentData['sequenceType'] === 'first'
        ) return;

        if (!isset($paymentData['amount']) || $paymentData['amount'] <= 0) {
            throw new \InvalidArgumentException('Ungültiger Zahlungsbetrag');
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
            'membership_id' => $paymentData['metadata']['membership_id'] ?? null,
            'mollie_payment_id' => $molliePayment->id,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'] ?? 'EUR',
            'description' => $paymentData['description'],
            'status' => $this->mapMollieStatus($molliePayment->status),
            'mollie_status' => $molliePayment->status,
            'payment_method' => $paymentData['method'],
            'checkout_url' => $molliePayment->getCheckoutUrl(),
            'user_id' => $paymentData['metadata']['user_id'] ?? null,
            'member_id' => $paymentData['metadata']['member_id'] ?? null,
            'invoice_id' => $paymentData['metadata']['invoice_id'] ?? null,
            'metadata' => $paymentData['metdata'] ?? null,
            'execution_date' => Carbon::now()->format('Y-m-d'),
            'due_date' => Carbon::now()->format('Y-m-d'),
            'created_at' => now(),
        ]);
    }

    /**
     * Update existing payment
     *
     * @param MolliePayment $molliePayment
     * @param Payment $payment
     * @param PaymentMethod $paymentMethod
     * @return void
     */
    protected function updatePayment(MolliePayment $molliePayment, Payment $payment, PaymentMethod $paymentMethod): void
    {
        $payment->update([
            'mollie_payment_id' => $molliePayment->id,
            'status' => $this->mapMollieStatus($molliePayment->status),
            'mollie_status' => $molliePayment->status,
            'payment_method' => $paymentMethod->type,
            'paid_date' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    /**
     * Store mollie payment method in local database as pending
     */
    public function storeMolliePaymentMethod(Member $member, string $type, string $customerId, ?Mandate $mollieMandate): PaymentMethod
    {
        return PaymentMethod::create([
            'member_id' => $member->id,
            'mollie_customer_id' => $customerId,
            'mollie_mandate_id' => $mollieMandate->id ?? null,
            'type' => 'mollie_' . str_replace('mollie_', '', $type),
            'status' => 'pending',
            'is_default' => true,
        ]);
    }

    /**
     * Takes care of manually adding or updating Mollie payment methods in the member file
     */
    public function handleMolliePaymentMethod(Member $member, PaymentMethod $paymentMethod): void
    {
        //$paymentMethod->type = 'mollie_directdebit'; // For testing purposes
        $mandateType = $member->gym->getMollieMandateType(str_replace('mollie_', '', $paymentMethod->type));

        if (
            !str_starts_with($paymentMethod->type, 'mollie_') ||
            !$mandateType
        ) return;

        if (!$paymentMethod->mollie_customer_id) {
            /** @var Customer $customer */
            $customer = $this->createCustomer($member->gym, $member->fullName(), $member->email);
        }
        $customerId = $customer->id ?? $paymentMethod->mollie_customer_id;

        /**
         * It is only possible to create mandates for IBANs and PayPal billing agreements with this endpoint.
         * To create mandates for cards, your customers need to perform a 'first payment' with their card.
         *
         * @var Mandate $mandate
         */
        if (in_array($mandateType, [MandateMethod::DIRECTDEBIT, MandateMethod::PAYPAL]) && !$paymentMethod->mollie_mandate_id) {
            $mandate = $this->createMandate($member->gym, $customerId, $paymentMethod, $member->fullName());
        }

        DB::beginTransaction();

        try {
            // Authorise a first credit card payment for subscription payments.
            if ($mandateType === MandateMethod::CREDITCARD) {
                $paymentData = [
                    'method' => $paymentMethod->type,
                    'amount' => 0,
                    'description' => '1. Zahlung zur Authorisierung',
                    'metadata' => [
                        'membership_id' => $member->memberships->first()?->id,
                        'member_id' => $member->id
                    ]
                ];
                $this->createFirstPayment($member->gym, $customerId, $paymentData);
            }

            $mandateId = $mandate->id ?? $this->getMandate($member->gym, $customerId)?->id;
            $status = $mandateId ? 'active' : 'pending';
            if ($status === 'active') {
                $member->update(['status' => 'active']);
                $membership = $member->memberships->first();
                $membership->update(['status' => 'active']);
            }

            $paymentMethod->update([
                'status' => $status,
                'mollie_customer_id' => $customerId,
                'mollie_mandate_id' => $mandateId,
                'iban' => ''
            ]);

            DB::commit();

        } catch (Exception $e) {
            DB::rollback();

            // Log the error for debugging
            Log::error('Handling of mollie payment method failed: ' . $e->getMessage());
        }
    }

    /**
     * Start mollie payment method in local database
     */
    public function activateMolliePaymentMethod(Gym $gym, string $memberId, string $type)
    {
        $paymentMethod = PaymentMethod::where('member_id', $memberId)
            ->where('type', 'mollie_' . str_replace('mollie_', '', $type))
            ->first();

        // Aktuelles Mandat von Mollie abrufen
        $mollieMandate = null;
        if ($paymentMethod->mollie_customer_id) {
            $mollieMandate = $this->getMandate($gym, $paymentMethod->mollie_customer_id);
        }

        PaymentMethod::where('id', $paymentMethod->id)
            ->update([
                'mollie_mandate_id' => $mollieMandate->id ?? null,
                'status' => 'active',
            ]);
    }

    /**
     * Folder Mollie status on local status
     */
    protected function mapMollieStatus(string $mollieStatus): string
    {
        return match($mollieStatus) {
            'open' => 'pending',
            'canceled' => 'canceled',
            'pending' => 'pending',
            'expired' => 'expired',
            'failed' => 'failed',
            'paid' => 'paid',
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
                    'paid_date' => now()
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
    public function createRefund(Gym $gym, string $paymentId, ?float $amount = null, ?string $description = null): Refund
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
    public function getRefunds(Gym $gym, string $paymentId): RefundCollection
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
    public function getPaymentStatistics(Gym $gym, ?Carbon $from = null, ?Carbon $to = null): array
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
            'successful_payments' => $payments->where('status', 'paid')->count(),
            'failed_payments' => $payments->where('status', 'failed')->count(),
            'pending_payments' => $payments->where('status', 'pending')->count(),
            'success_rate' => $payments->count() > 0 ?
                round(($payments->where('status', 'paid')->count() / $payments->count()) * 100, 2) : 0
        ];
    }
}
