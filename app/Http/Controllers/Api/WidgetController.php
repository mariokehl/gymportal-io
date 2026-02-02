<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\WidgetAnalytics;
use App\Models\WidgetRegistration;
use App\Services\WidgetService;
use App\Util\MembershipPriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WidgetController extends Controller
{
    public function __construct(
        private WidgetService $widgetService
    ) {}

    /**
     * Tarif-Auswahl HTML zurückgeben
     */
    public function getPlansMarkup(Request $request)
    {
        $gymId = $this->getGymIdFromRequest($request);
        $sessionId = $request->header('X-Widget-Session');

        // Session-Daten cleanup bei neuem Plans-Aufruf
        $this->cleanupWidgetSession($sessionId);

        // Gym-Daten
        $gym = Gym::findOrFail($gymId);
        $gymData = [
            'widget_settings' => $gym->widget_settings,
        ];

        // Hole die konfigurierten Verträge aus den Widget-Einstellungen
        $selectedContractIds = $gym->widget_settings['contracts']['selected_ids'] ?? [];
        $autoSort = $gym->widget_settings['contracts']['auto_sort'] ?? false;

        // Query Builder für die Verträge
        $plansQuery = MembershipPlan::where('gym_id', $gymId)
            ->where('is_active', true);

        // Wenn Verträge in den Einstellungen ausgewählt wurden
        if (!empty($selectedContractIds)) {
            // Nur die ausgewählten Verträge laden
            $plansQuery->whereIn('id', $selectedContractIds);

            if ($autoSort) {
                // Automatische Sortierung nach Preis (aufsteigend)
                $plans = $plansQuery
                    ->orderBy('price', 'asc')
                    ->get();
            } else {
                // Manuelle Sortierung: Behalte die Reihenfolge aus den Einstellungen
                $plans = $plansQuery->get();

                // Sortiere die Collection basierend auf der Reihenfolge in selected_ids
                $plans = $plans->sortBy(function ($plan) use ($selectedContractIds) {
                    return array_search($plan->id, $selectedContractIds);
                })->values();
            }
        } else {
            // Fallback: Wenn keine Verträge konfiguriert sind, zeige alle aktiven
            $plans = $plansQuery
                ->orderBy('sort_order')
                ->orderBy('price', 'asc')
                ->get();
        }

        $html = view('widget.plans', compact('plans', 'gymData'))->render();

        return response()->json([
            'html' => $html,
            'success' => true,
            'session_cleaned' => true,
            'plans_count' => $plans->count(),
            'using_configured_plans' => !empty($selectedContractIds)
        ]);
    }

    /**
     * Persönliche Daten Formular HTML zurückgeben
     */
    public function getFormMarkup(Request $request)
    {
        $gymId = $this->getGymIdFromRequest($request);
        $sessionId = $request->header('X-Widget-Session');

        // Gym-Daten
        $gym = Gym::findOrFail($gymId);

        // Zahlungsmethoden aus dem Gym Model laden
        $paymentMethods = $this->getAvailablePaymentMethods($gym);

        $gymData = [
            'id' => $gym->id,
            'name' => $gym->name,
            'widget_settings' => $gym->widget_settings,
            'payment_methods' => $paymentMethods
        ];

        // Gespeicherte Formulardaten abrufen
        $savedFormData = $this->getWidgetSessionData($sessionId, 'form_data');
        $selectedPlan = $this->getWidgetSessionData($sessionId, 'selected_plan');

        $selectedPlan = $selectedPlan ?: $request->input('plan_id');
        $plan = null;

        if ($selectedPlan) {
            $plan = MembershipPlan::where('gym_id', $gymId)
                ->where('id', $selectedPlan)
                ->first();
        }

        $html = view('widget.form', compact('plan', 'savedFormData', 'gymData'))->render();

        return response()->json([
            'html' => $html,
            'success' => true,
            'has_saved_data' => !empty($savedFormData),
            'payment_methods_count' => count($paymentMethods)
        ]);
    }

    /**
     * Verfügbare Zahlungsmethoden für das Widget abrufen
     */
    private function getAvailablePaymentMethods(Gym $gym): array
    {
        $methods = [];

        // Standard-Zahlungsmethoden (nur aktivierte)
        $standardMethods = $gym->getEnabledStandardPaymentMethods();
        foreach ($standardMethods as $method) {
            $methods[] = [
                'key' => $method['key'],
                'name' => $method['name'],
                'description' => $method['description'],
                'type' => 'standard',
                'icon' => $method['icon'],
                'requires_mandate' => $method['requires_mandate'] ?? false,
            ];
        }

        // Mollie-Zahlungsmethoden
        if ($gym->hasMollieConfigured()) {
            $mollieMethods = $gym->getMolliePaymentMethods();
            foreach ($mollieMethods as $method) {
                $methods[] = [
                    'key' => $method['key'],
                    'name' => $method['name'],
                    'description' => $method['description'],
                    'type' => 'mollie',
                    'mollie_method_id' => $method['mollie_method_id'],
                ];
            }
        }

        return $methods;
    }

    /**
     * Checkout/Bestätigung HTML zurückgeben
     */
    public function getCheckoutMarkup(Request $request)
    {
        $gymId = $this->getGymIdFromRequest($request);
        $sessionId = $request->header('X-Widget-Session');

        // Aktuelle Session-Daten abrufen
        $formData = $this->getWidgetSessionData($sessionId, 'form_data') ?: [];
        $selectedPlan = $this->getWidgetSessionData($sessionId, 'selected_plan');

        $planData = [];
        if ($selectedPlan) {
            $plan = MembershipPlan::where('gym_id', $gymId)
                ->where('id', $selectedPlan)
                ->first();

            if ($plan) {
                $planData = $this->preparePlanDataForSession($plan);
            }
        }

        // Gym-Daten
        $gym = Gym::find($gymId);
        $gymData = [
            'id' => $gym->id,
            'name' => $gym->name,
            'widget_settings' => $gym->widget_settings,
            'contracts_start_first_of_month' => $gym->contracts_start_first_of_month,
            'free_trial_membership_name' => $gym->free_trial_membership_name,
        ];

        $html = view('widget.checkout', compact('formData', 'planData', 'gymData'))->render();

        return response()->json([
            'html' => $html,
            'success' => true,
            'checkout_data' => [
                'plan' => $planData,
                'member' => $this->sanitizeFormDataForDisplay($formData),
                'gym' => $gymData
            ]
        ]);
    }

    /**
     * Formulardaten zwischenspeichern
     */
    public function saveFormData(Request $request)
    {
        $sessionId = $request->header('X-Widget-Session');

        if (!$sessionId) {
            return response()->json(['success' => false, 'error' => 'No session ID']);
        }

        $formData = $request->input('form_data', []);
        $selectedPlan = $request->input('selected_plan');

        // Sensitive Daten nicht in Cache speichern
        $sanitizedData = $this->sanitizeFormDataForStorage($formData);

        // Session-Daten speichern (30 Minuten TTL)
        $this->setWidgetSessionData($sessionId, 'form_data', $sanitizedData);

        if ($selectedPlan) {
            $this->setWidgetSessionData($sessionId, 'selected_plan', $selectedPlan);
        }

        return response()->json([
            'success' => true,
            'saved_fields' => count($sanitizedData),
            'session_id' => $sessionId
        ]);
    }

    /**
     * Mitgliedschaft erstellen - erweitert um Mollie-Support
     */
    public function createContract(Request $request)
    {
        $gymId = $this->getGymIdFromRequest($request);
        $sessionId = $request->header('X-Widget-Session');

        // Atomically acquire submission lock to prevent duplicate submissions
        // Cache::add is atomic - only one request can acquire the lock
        if (!$this->acquireSubmissionLock($sessionId)) {
            return response()->json([
                'success' => false,
                'error' => 'Duplicate submission detected'
            ], 429);
        }

        // Erweiterte Validierung
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|indisposable|max:255',
            'phone' => 'required|string|max:20',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'plan_id' => 'required|exists:membership_plans,id',
            'payment_method' => 'required|string',
            'iban' => 'required_if:payment_method,sepa_direct_debit|required_if:payment_method,mollie_directdebit|nullable|string|min:15|max:34',
            'account_holder' => 'required_if:payment_method,sepa_direct_debit|required_if:payment_method,mollie_directdebit|nullable|string',
            'sepa_mandate_acknowledged' => 'sometimes|boolean',
        ]);

        // Spezielle SEPA-Validierung
        if ($request->payment_method === 'sepa_direct_debit' || $request->payment_method === 'mollie_directdebit') {
            $validator->sometimes('sepa_mandate_acknowledged', 'required|accepted', function ($input) {
                return in_array($input->payment_method, ['sepa_direct_debit', 'mollie_directdebit']);
            });
        }

        if ($validator->fails()) {
            $this->clearSubmissionLock($sessionId);
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Gym laden
            $gym = Gym::findOrFail($gymId);

            // Plan laden
            $plan = MembershipPlan::where('gym_id', $gymId)
                ->where('id', $request->plan_id)
                ->where('is_active', true)
                ->firstOrFail();

            // Doppelte E-Mail-Registrierung prüfen
            $existingMember = $this->widgetService->validateEmail($gym, $request->email);

            if (!$existingMember['valid']) {
                $this->clearSubmissionLock($sessionId);
                return response()->json([
                    'success' => false,
                    'error' => 'Diese E-Mail-Adresse ist bereits registriert.',
                    'field' => 'email'
                ], 422);
            }

            $this->widgetService->initializeRegistration($gym, [
                'plan_id' => $plan->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'widget_session' => $sessionId,
            ]);

            // Registrierungsdaten vorbereiten
            $registrationData = [
                'plan_id' => $plan->id,
                'salutation' => $request->salutation,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'birth_date' => $request->birth_date,
                'address' => $request->address,
                'address_addition' => $request->address_addition,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'country' => $request->country ?? 'DE',
                'voucher_code' => $request->voucher_code,
                'fitness_goals' => $request->fitness_goals,
                'payment_method' => $request->payment_method,
                'widget_session' => $sessionId,
            ];

            // SEPA-spezifische Daten hinzufügen
            if ($request->payment_method === 'sepa_direct_debit' || $request->payment_method === 'mollie_directdebit') {
                $registrationData['iban'] = $request->iban;
                $registrationData['account_holder'] = $request->account_holder;
                $registrationData['sepa_mandate_acknowledged'] = $request->boolean('sepa_mandate_acknowledged');
            }

            $result = $this->widgetService->processRegistration($gym, $registrationData);

            // Session-Daten cleanup nach erfolgreicher Registrierung (außer bei Mollie)
            if (!isset($result['requires_payment']) || !$result['requires_payment']) {
                $this->cleanupWidgetSession($sessionId);
            }

            // Submission-Lock entfernen
            $this->clearSubmissionLock($sessionId);

            return response()->json($result);

        } catch (\Exception $e) {
            // Submission-Lock entfernen bei Fehler
            $this->clearSubmissionLock($sessionId);

            logger()->error('Widget contract creation failed', [
                'gym_id' => $gymId,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'request_data' => $request->except(['iban', 'password']),
                'payment_method' => $request->payment_method
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.',
                'error_code' => 'CREATION_FAILED'
            ], 500);
        }
    }

    /**
     * Mollie Payment Return verarbeiten
     */
    public function handleMollieReturn(Request $request, int $gymId, string $widgetSession)
    {
        try {
            $gym = Gym::findOrFail($gymId);
            $widgetRegistration = WidgetRegistration::where('form_data', 'like', '%"widget_session":"' . $widgetSession . '"%')->latest()->first();
            $paymentId = $widgetRegistration->payment_data['mollie_payment_id'] ?? false;

            if (!$paymentId) {
                return $this->renderMollieResult([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Fehlende Payment-ID.',
                ], $gym);
            }

            // Payment-Status von WidgetService verarbeiten lassen
            $result = $this->widgetService->processMollieReturn($gym, $widgetSession, $paymentId);

            // Analytics tracken
            $this->widgetService->trackEvent($gym, 'mollie_return_processed', 'payment_return', [
                'session_id' => $widgetSession,
                'payment_id' => $paymentId,
                'status' => $result['status'],
                'success' => $result['success']
            ]);

            // Session cleanup bei erfolgreichem Payment
            if ($result['success'] && $result['status'] === 'paid') {
                $this->cleanupWidgetSession($widgetSession);
            }

            return $this->renderMollieResult($result, $gym);

        } catch (\Exception $e) {
            Log::error('Mollie return handling failed', [
                'gym_id' => $gymId,
                'session_id' => $widgetSession,
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return $this->renderMollieResult([
                'success' => false,
                'status' => 'error',
                'message' => 'Fehler bei der Zahlungsverarbeitung.',
            ], Gym::find($gymId));
        }
    }

    /**
     * Mollie-Result-Page rendern
     */
    private function renderMollieResult(array $result, ?Gym $gym): \Illuminate\Http\Response
    {
        $gymData = $gym ? [
            'id' => $gym->id,
            'name' => $gym->name,
            'widget_settings' => $gym->widget_settings ?? []
        ] : null;

        $html = view('widget.mollie-result', compact('result', 'gymData'))->render();

        return response($html);
    }

    /**
     * Mollie Payment-Status abfragen (AJAX-Endpoint)
     */
    public function checkMolliePaymentStatus(Request $request)
    {
        try {
            $gymId = $this->getGymIdFromRequest($request);
            $widgetSession = $request->header('X-Widget-Session');
            $paymentId = $request->input('payment_id');

            if (!$paymentId) {
                return response()->json(['error' => 'Payment ID required'], 400);
            }

            $widgetRegistration = WidgetRegistration::where('form_data', 'like', '%"widget_session":"' . $widgetSession . '"%')->select('session_id')->latest()->first();
            $sessionId = $widgetRegistration->session_id ?? 'unknown';

            $gym = Gym::findOrFail($gymId);
            $result = $this->widgetService->processMollieReturn($gym, $sessionId, $paymentId, sendNotifications: false);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Mollie payment status check failed', [
                'error' => $e->getMessage(),
                'payment_id' => $request->input('payment_id')
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Status konnte nicht abgerufen werden.'
            ], 500);
        }
    }

    /**
     * Gym ID aus Request extrahieren
     */
    private function getGymIdFromRequest(Request $request)
    {
        $studioId = $request->header('X-Studio-ID');
        $apiKey = $request->header('X-API-Key');

        // Beide Header sind Pflicht
        if (!$apiKey) {
            throw new AuthenticationException('API Key ist erforderlich');
        }

        if (!$studioId) {
            throw new AuthenticationException('Studio ID ist erforderlich');
        }

        // Gym über API Key finden
        $gym = \App\Models\Gym::where('api_key', $apiKey)->first();

        if (!$gym) {
            throw new AuthorizationException('Ungültiger API Key');
        }

        // Studio ID muss zum API Key passen
        if ($gym->id != $studioId) {
            throw new AuthorizationException('Studio ID stimmt nicht mit API Key überein');
        }

        return $gym->id;
    }

    /**
     * Bereitet Plan-Daten für die Session vor
     */
    private function preparePlanDataForSession(MembershipPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'price' => $plan->price,
            'billing_cycle' => $plan->billing_cycle,
            'setup_fee' => $plan->setup_fee,
            'membership_price' => (new MembershipPriceCalculator)->calculateTotalPrice(
                $plan->price,
                $plan->billing_cycle,
                $plan->commitment_months,
                $plan->setup_fee
            ),
            'currency' => 'EUR',
            'commitment_months' => $plan->commitment_months,
            'cancellation_period_days' => $plan->cancellation_period_days,
            'gym_id' => $plan->gym_id,
            'selected_at' => now()->toISOString()
        ];
    }

    /**
     * Session-Daten cleanup mit atomarem Lock.
     * Stellt sicher, dass alle Session-Daten konsistent gelöscht werden.
     */
    private function cleanupWidgetSession(string $sessionId): bool
    {
        if (!$sessionId) return false;

        $lockKey = "lock:widget_session:{$sessionId}:cleanup";
        $lock = Cache::lock($lockKey, 10);

        try {
            if ($lock->block(5)) {
                $keys = [
                    "widget_session:{$sessionId}:form_data",
                    "widget_session:{$sessionId}:selected_plan",
                    "widget_session:{$sessionId}:step",
                    "widget_session:{$sessionId}:validation_errors",
                    "widget_session:{$sessionId}:submission_lock",
                ];

                foreach ($keys as $key) {
                    Cache::forget($key);
                }

                logger()->debug('Widget session cleaned up', ['session_id' => $sessionId]);
                return true;
            }

            Log::warning('Failed to acquire cleanup lock for widget session', [
                'session_id' => $sessionId,
            ]);
            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * Widget Session-Daten setzen mit atomarem Lock.
     * Verhindert Race Conditions bei gleichzeitigen Schreiboperationen.
     */
    private function setWidgetSessionData(string $sessionId, string $key, $value): bool
    {
        $cacheKey = "widget_session:{$sessionId}:{$key}";
        $lock = Cache::lock("lock:{$cacheKey}", 10); // 10 second lock timeout

        try {
            // Block for up to 5 seconds waiting for lock
            if ($lock->block(5)) {
                Cache::put($cacheKey, $value, now()->addMinutes(30));
                return true;
            }

            Log::warning('Failed to acquire cache lock for widget session', [
                'session_id' => $sessionId,
                'key' => $key,
            ]);
            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * Widget Session-Daten abrufen
     */
    private function getWidgetSessionData(string $sessionId, string $key)
    {
        return Cache::get("widget_session:{$sessionId}:{$key}");
    }

    /**
     * Atomically update widget session data using a callback.
     * Useful for read-modify-write operations.
     */
    private function updateWidgetSessionData(string $sessionId, string $key, callable $callback): bool
    {
        $cacheKey = "widget_session:{$sessionId}:{$key}";
        $lock = Cache::lock("lock:{$cacheKey}", 10);

        try {
            if ($lock->block(5)) {
                $currentValue = Cache::get($cacheKey);
                $newValue = $callback($currentValue);
                Cache::put($cacheKey, $newValue, now()->addMinutes(30));
                return true;
            }

            Log::warning('Failed to acquire cache lock for widget session update', [
                'session_id' => $sessionId,
                'key' => $key,
            ]);
            return false;
        } finally {
            $lock->release();
        }
    }

    /**
     * Atomically acquire submission lock to prevent duplicate submissions.
     * Uses Cache::add() which only sets the value if the key doesn't exist (atomic operation).
     *
     * @return bool True if lock was acquired (no duplicate), false if already locked (duplicate)
     */
    private function acquireSubmissionLock(string $sessionId): bool
    {
        // Cache::add is atomic - returns true only if key didn't exist and was set
        return Cache::add(
            "widget_session:{$sessionId}:submission_lock",
            [
                'locked_at' => now()->toISOString(),
                'pid' => getmypid(),
            ],
            now()->addMinutes(5)
        );
    }

    /**
     * Submission-Lock entfernen
     */
    private function clearSubmissionLock(string $sessionId): void
    {
        Cache::forget("widget_session:{$sessionId}:submission_lock");
    }

    /**
     * Formulardaten für Speicherung bereinigen
     */
    private function sanitizeFormDataForStorage(array $data): array
    {
        // Sensitive Daten nicht in Cache speichern
        $allowedFields = [
            'first_name',
            'last_name',
            'email',
            'phone',
            'address',
            'address_addition',
            'city',
            'postal_code',
            'country',
            'birth_date',
            'payment_method'
        ];

        $sanitized = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $sanitized[$field] = $data[$field];
            }
        }

        return $sanitized;
    }

    /**
     * Formulardaten für Anzeige bereinigen
     */
    private function sanitizeFormDataForDisplay(array $data): array
    {
        $displayData = [];

        // Standard-Felder
        $fields = [
            'first_name', 'last_name', 'email', 'phone',
            'address', 'address_addition', 'city', 'postal_code',
            'salutation', 'fitness_goals'
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $displayData[$field] = $data[$field];
            }
        }

        // IBAN maskieren für Anzeige
        if (isset($data['iban'])) {
            $iban = $data['iban'];
            $displayData['iban'] = strlen($iban) > 8 ?
                substr($iban, 0, 4) . '****' . substr($iban, -4) :
                '****';
        }

        return $displayData;
    }

    /**
     * Analytics-Event tracken
     */
    public function trackAnalytics(Request $request)
    {
        $gymId = $this->getGymIdFromRequest($request);

        if (!$gymId) {
            return response()->json([
                'success' => false,
                'error' => 'Gym not found'
            ], 400);
        }

        // Validierung der Analytics-Daten
        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string|max:50',
            'step' => 'nullable|string|max:50',
            'data' => 'nullable|array',
            'session_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Analytics-Event erstellen
            $analytics = WidgetAnalytics::create([
                'gym_id' => $gymId,
                'event_type' => $request->input('event_type'),
                'step' => $request->input('step'),
                'data' => $request->input('data', []),
                'session_id' => $request->input('session_id'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referrer' => $request->header('referer'),
                'created_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'event_id' => $analytics->id
            ]);

        } catch (\Exception $e) {
            // Fehler loggen, aber nicht an Client weiterleiten
            logger()->error('Widget Analytics Error: ' . $e->getMessage(), [
                'gym_id' => $gymId,
                'event_type' => $request->input('event_type'),
                'ip' => $request->ip()
            ]);

            // Immer success=true zurückgeben, damit Widget weiter funktioniert
            return response()->json(['success' => true]);
        }
    }

    /**
     * Analytics-Statistiken abrufen
     */
    public function getAnalyticsStats(Request $request, $gymId = null)
    {
        $gymId = $gymId ?: $this->getGymIdFromRequest($request);

        if (!$gymId) {
            return response()->json(['error' => 'Gym not found'], 400);
        }

        $days = $request->input('days', 30);
        $startDate = Carbon::now()->subDays($days);

        try {
            $stats = [
                'events' => WidgetAnalytics::where('gym_id', $gymId)
                    ->where('created_at', '>=', $startDate)
                    ->selectRaw('event_type, COUNT(*) as count')
                    ->groupBy('event_type')
                    ->get()
                    ->pluck('count', 'event_type'),

                'daily_events' => WidgetAnalytics::where('gym_id', $gymId)
                    ->where('created_at', '>=', $startDate)
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),

                'conversion_funnel' => $this->getConversionFunnel($gymId, $startDate),

                'popular_plans' => $this->getPopularPlans($gymId, $startDate),

                // Mollie-spezifische Statistiken
                'mollie_stats' => $this->getMollieStats($gymId, $startDate),
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Could not retrieve analytics'
            ], 500);
        }
    }

    /**
     * Conversion-Funnel berechnen
     */
    private function getConversionFunnel($gymId, $startDate)
    {
        $events = WidgetAnalytics::where('gym_id', $gymId)
            ->where('created_at', '>=', $startDate)
            ->whereIn('event_type', ['view', 'plan_selected', 'form_started', 'form_completed', 'registration_completed', 'mollie_payment_completed'])
            ->selectRaw('event_type, COUNT(DISTINCT session_id) as unique_sessions')
            ->groupBy('event_type')
            ->get()
            ->pluck('unique_sessions', 'event_type');

        return [
            'views' => $events->get('view', 0),
            'plan_selections' => $events->get('plan_selected', 0),
            'form_starts' => $events->get('form_started', 0),
            'form_completions' => $events->get('form_completed', 0),
            'registrations' => $events->get('registration_completed', 0),
            'mollie_payments' => $events->get('mollie_payment_completed', 0),
        ];
    }

    /**
     * Beliebte Pläne ermitteln
     */
    private function getPopularPlans($gymId, $startDate)
    {
        return WidgetAnalytics::where('gym_id', $gymId)
            ->where('created_at', '>=', $startDate)
            ->where('event_type', 'plan_selected')
            ->whereNotNull('data->plan_id')
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.plan_id")) as plan_id, COUNT(*) as selections')
            ->groupBy('plan_id')
            ->orderBy('selections', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Mollie-spezifische Statistiken
     */
    private function getMollieStats($gymId, $startDate)
    {
        $molliePayments = Payment::where('gym_id', $gymId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                method,
                status,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            ')
            ->groupBy('method', 'status')
            ->get();

        // Payment-Method-Performance
        $methodStats = $molliePayments->groupBy('method')->map(function ($payments, $method) {
            $total = $payments->sum('count');
            $paid = $payments->where('status', 'paid')->sum('count');
            $pending = $payments->where('status', 'pending')->sum('count');
            $failed = $payments->where('status', 'failed')->sum('count');

            return [
                'method' => $method,
                'total_payments' => $total,
                'paid' => $paid,
                'pending' => $pending,
                'failed' => $failed,
                'success_rate' => $total > 0 ? round(($paid / $total) * 100, 2) : 0,
                'total_amount' => $payments->sum('total_amount'),
                'avg_amount' => $payments->avg('avg_amount'),
            ];
        });

        return [
            'total_payments' => $molliePayments->sum('count'),
            'total_amount' => $molliePayments->sum('total_amount'),
            'avg_amount' => $molliePayments->avg('avg_amount'),
            'method_breakdown' => $methodStats->values(),
            'overall_success_rate' => $molliePayments->sum('count') > 0
                ? round(($molliePayments->where('status', 'paid')->sum('count') / $molliePayments->sum('count')) * 100, 2)
                : 0,
        ];
    }
}
