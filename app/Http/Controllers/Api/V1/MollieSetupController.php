<?php
// app/Http/Controllers/Api/V1/MollieSetupController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\User;
use App\Services\MollieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MollieSetupController extends Controller
{
    protected $mollieService;

    public function __construct(MollieService $mollieService)
    {
        $this->mollieService = $mollieService;
    }

    /**
     * Validate API key and optional OAuth token
     */
    public function validateCredentials(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string|min:30',
            'oauth_token' => 'nullable|string|min:10',
            'test_mode' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Test the Mollie API connection with API key
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $request->api_key,
                'Content-Type' => 'application/json'
            ])->get('https://api.mollie.com/v2/methods');

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ungültiger API-Schlüssel'
                ], 400);
            }

            // Get available payment methods
            $methods = $response->json()['_embedded']['methods'] ?? [];

            $result = [
                'success' => true,
                'api_key_valid' => true,
                'methods' => $methods,
                'message' => 'API-Schlüssel erfolgreich validiert'
            ];

            // Validate OAuth token if provided
            if (!empty($request->oauth_token)) {
                $tokenValidation = $this->mollieService->validateOAuthToken($request->oauth_token);

                $result['oauth_token_valid'] = $result['success'] = $tokenValidation['valid'];
                $result['oauth_validation'] = $tokenValidation;

                if (!$tokenValidation['valid']) {
                    $result['message'] = 'OAuth-Token Validierung fehlgeschlagen: ' . $tokenValidation['message'];
                } else {
                    $result['message'] .= 'und OAuth-Token erfolgreich validiert';
                }
            } else {
                $result['oauth_token_valid'] = null; // Not provided
            }

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler bei der Validierung der Anmeldeinformationen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save Mollie configuration
     */
    public function saveConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string|min:30',
            'oauth_token' => 'nullable|string|min:10',
            'test_mode' => 'boolean',
            'enabled_methods' => 'required|array|min:1',
            'enabled_methods.*' => 'string',
            'webhook_url' => 'nullable|url',
            'redirect_url' => 'nullable|url',
            'description_prefix' => 'nullable|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            /** @var User $user */
            $user = $request->user();

            /** @var Gym $gym */
            $gym = $user->currentGym;

            $config = [
                'api_key' => $request->api_key,
                'oauth_token' => $request->filled('oauth_token') ? $request->oauth_token : null,
                'test_mode' => $request->test_mode ?? false,
                'enabled_methods' => $request->enabled_methods,
                'redirect_url' => $request->redirect_url ?: route('payment.return', ['organization' => $gym->id]), // TODO: Implement return route
                'description_prefix' => $request->description_prefix ?: $gym->name,
                'configured_at' => now(),
                'configured_by' => Auth::id(),
            ];

            // Set webhook only if oauth_token is present
            if ($request->filled('oauth_token')) {
                $webhookUrl = $request->webhook_url ?: route('v1.public.mollie.webhook');
                $webhookId = $this->createMollieWebhook($request->oauth_token, $webhookUrl, $request->test_mode);

                $config['webhook_url'] = $webhookUrl;
                $config['webhook_id'] = $webhookId;
            }

            $gym->update(['mollie_config' => $config]);

            return response()->json([
                'success' => true,
                'message' => 'Mollie-Integration erfolgreich konfiguriert'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Speichern der Konfiguration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test the Mollie integration
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testIntegration(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            /** @var Gym $gym */
            $gym = $user->ownedGyms()->find($request->organization_id);

            Log::info('Mollie test integration started', [
                'user_id' => $user->id,
                'api_key_length' => strlen($gym->mollie_config['api_key']),
                'test_mode' => $gym->mollie_config['test_mode'],
            ]);

            if (!$this->mollieService->isConfigured($gym)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mollie ist noch nicht konfiguriert'
                ], 400);
            }

            // Create a test payment of 1 Euro
            $payment = $this->mollieService->createPayment($gym, [
                'amount' => 1.00,
                'description' => 'Test-Zahlung für ' . $gym->name,
                'method' => 'creditcard' // or any other available method
            ]);

            return response()->json([
                'success' => true,
                'payment_url' => $payment->getCheckoutUrl(),
                'payment_id' => $payment->id,
                'message' => 'Test-Zahlung erfolgreich erstellt'
            ]);

        } catch (\Exception $e) {
            Log::error('Mollie integration test failed', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Testen der Integration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check webhook status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkWebhookStatus(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            /** @var Gym $gym */
            $gym = $user->ownedGyms()->find($request->organization_id);

            $config = $gym->mollie_config;

            if (!$config || !isset($config['webhook_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kein Webhook konfiguriert'
                ]);
            }

            $queryParam = $config['test_mode'] ? "?testmode=true" : "";
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $config['oauth_token']
            ])->get("https://api.mollie.com/v2/webhooks/{$config['webhook_id']}" . $queryParam);

            if ($response->successful()) {
                $webhook = $response->json();
                return response()->json([
                    'success' => true,
                    'webhook' => $webhook,
                    'status' => $webhook['status'] ?? 'unknown'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Webhook nicht gefunden'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Prüfen des Webhook-Status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create webhook in Mollie
     *
     * @param string $oauthToken
     * @param string $webhookUrl
     * @param boolean $testMode
     * @return string The webhooks idenitifier
     * @throws \Exception If webhook could not be created
     */
    private function createMollieWebhook(string $oauthToken, string $webhookUrl, bool $testMode): string
    {
        // TODO: Check if the webhook URL has changed. If so, update it.
        // ...

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $oauthToken,
            'Content-Type' => 'application/json'
        ])->post('https://api.mollie.com/v2/webhooks', [
            'url' => !app()->environment('local') ? $webhookUrl : request()->getSchemeAndHttpHost() . '/api/v1/public/mollie/webhook',
            'name' => 'Webhook #1 (autogenerated by gymportal.io)',
            'eventTypes' => 'payment-link.paid',
            'testmode' => $testMode,
        ]);

        if ($response->successful()) {
            return $response->json()['id'];
        }

        throw new \Exception('Webhook konnte nicht erstellt werden: ' . $response->body());
    }

    /**
     * Remove Mollie configuration
     */
    public function removeConfiguration()
    {
        try {
            $tenant = Auth::user()->tenant;
            $config = $tenant->mollie_config;

            // Delete webhook in Mollie if present
            if ($config && isset($config['webhook_id'])) {
                try {
                    Http::withHeaders([
                        'Authorization' => 'Bearer ' . $config['api_key']
                    ])->delete("https://api.mollie.com/v2/profiles/me/webhooks/{$config['webhook_id']}");
                } catch (\Exception $e) {
                    // Webhook deletion failed, but we're continuing anyway
                }
            }

            $tenant->update(['mollie_config' => null]);

            return response()->json([
                'success' => true,
                'message' => 'Mollie-Integration erfolgreich entfernt'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Entfernen der Konfiguration: ' . $e->getMessage()
            ], 500);
        }
    }
}
