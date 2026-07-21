<?php
// app/Http/Controllers/Api/V1/MollieSetupController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\User;
use App\Services\MollieService;
use Exception;
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
            ])->get('https://api.mollie.com/v2/methods?sequenceType=recurring'); // Set it to recurring to only return enabled methods that can be used for recurring payments or subscriptions.

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

        } catch (Exception $e) {
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

            $prevConfig = $this->mollieService->getConfig($gym);
            MollieService::deleteWebhookIfAny($prevConfig, $gym->id);

            $config = [
                'api_key' => $request->api_key,
                'oauth_token' => $request->filled('oauth_token') ? $request->oauth_token : null,
                'test_mode' => $request->test_mode ?? false,
                'enabled_methods' => $request->enabled_methods,
                'redirect_url' => $request->redirect_url ?: route('payment.return', ['organization' => $gym->id]),
                'description_prefix' => $request->description_prefix ?: $gym->name,
                'configured_at' => now(),
                'configured_by' => Auth::id(),
            ];

            // Set webhook only if oauth_token is present
            if ($request->filled('oauth_token')) {
                $webhookUrl = $request->webhook_url ?: route('v1.public.mollie.webhook');

                // A Mollie account holds only one webhook per URL, so adopt an existing one
                // instead of creating a duplicate (e.g. a second gym on the same account)
                $webhook = MollieService::findOrCreateWebhook(
                    $request->oauth_token,
                    $webhookUrl,
                    $request->boolean('test_mode')
                );

                $config['webhook_url'] = $webhookUrl;
                $config['webhook_id'] = $webhook['id'];
            }

            $gym->update(['mollie_config' => $config]);

            return response()->json([
                'success' => true,
                'message' => 'Mollie-Integration erfolgreich konfiguriert'
            ]);

        } catch (Exception $e) {
            Log::error('Mollie configuration could not be saved', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Die Mollie-Konfiguration konnte nicht gespeichert werden.',
                'detail' => $this->extractMollieErrorDetail($e->getMessage())
            ], 500);
        }
    }

    /**
     * Turn a Mollie API error into a readable detail message.
     * Mollie errors arrive as an embedded JSON body, which is unreadable in the UI.
     *
     * @param string $message
     * @return string
     */
    protected function extractMollieErrorDetail(string $message): string
    {
        if (preg_match('/\{.*\}/s', $message, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded) && !empty($decoded['detail'])) {
                return $decoded['detail'];
            }
        }

        return $message;
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
                'method' => $gym->mollie_config['enabled_methods'][0] ?: 'creditcard' // or any other available method
            ]);

            return response()->json([
                'success' => true,
                'payment_url' => $payment->getCheckoutUrl(),
                'payment_id' => $payment->id,
                'message' => 'Test-Zahlung erfolgreich erstellt'
            ]);

        } catch (Exception $e) {
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
     * Check in the background whether a webhook for the given URL is already registered at Mollie.
     * Runs during the setup wizard, so the OAuth token may not be persisted yet.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function lookupWebhook(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'oauth_token' => 'nullable|string|min:10',
            'webhook_url' => 'required|url',
            'test_mode' => 'boolean'
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

            $config = $gym ? $this->mollieService->getConfig($gym) : [];

            // Prefer the token currently entered in the wizard, fall back to the stored one
            $oauthToken = $request->filled('oauth_token')
                ? $request->oauth_token
                : ($config['oauth_token'] ?? null);

            if (!$oauthToken) {
                return response()->json([
                    'success' => false,
                    'exists' => false,
                    'message' => 'Kein OAuth-Token verfügbar'
                ], 422);
            }

            $webhook = MollieService::findWebhookByUrl(
                $oauthToken,
                $request->webhook_url,
                $request->boolean('test_mode')
            );

            if (!$webhook) {
                return response()->json([
                    'success' => true,
                    'exists' => false,
                    'message' => 'Für diese URL ist noch kein Webhook angelegt'
                ]);
            }

            return response()->json([
                'success' => true,
                'exists' => true,
                'webhook' => [
                    'id' => $webhook['id'] ?? null,
                    'name' => $webhook['name'] ?? null,
                    'url' => $webhook['url'] ?? null,
                    'status' => $webhook['status'] ?? 'unknown'
                ],
                'message' => 'Für diese URL ist bereits ein Webhook angelegt'
            ]);

        } catch (Exception $e) {
            Log::warning('Mollie webhook lookup failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook konnte nicht geprüft werden'
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

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Prüfen des Webhook-Status: ' . $e->getMessage()
            ], 500);
        }
    }
}
