<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\WidgetAnalytics;
use App\Services\WidgetService;
use App\Util\MembershipPriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;

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

        $plans = MembershipPlan::where('gym_id', $gymId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price', 'asc')
            ->get();

        $html = view('widget.plans', compact('plans', 'gymData'))->render();

        return response()->json([
            'html' => $html,
            'success' => true,
            'session_cleaned' => true
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
        $gymData = [
            'id' => $gym->id,
            'name' => $gym->name,
            'widget_settings' => $gym->widget_settings,
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
            'has_saved_data' => !empty($savedFormData)
        ]);
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
     * Mitgliedschaft erstellen
     */
    public function createContract(Request $request)
    {
        $gymId = $this->getGymIdFromRequest($request);
        $sessionId = $request->header('X-Widget-Session');

        // Prüfe auf doppelte Submissions
        if ($this->isDuplicateSubmission($sessionId)) {
            return response()->json([
                'success' => false,
                'error' => 'Duplicate submission detected'
            ], 429);
        }

        // Markiere Submission als in Bearbeitung
        $this->markSubmissionInProgress($sessionId);

        // Validierung
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'birth_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'plan_id' => 'required|exists:membership_plans,id',
            'iban' => 'nullable|string|max:34',
            'account_holder' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validierung
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:20',
                'plan_id' => 'required|exists:membership_plans,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Gym laden
            $gym = Gym::findOrFail($gymId);

            // Plan laden
            $plan = MembershipPlan::where('gym_id', $gymId)
                ->where('id', $request->plan_id)
                ->where('is_active', true)
                ->firstOrFail();

            // Doppelte E-Mail-Registrierung prüfen
            $existingMember = Member::where('gym_id', $gymId)
                ->where('email', $request->email)
                ->first();

            if ($existingMember) {
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

            $result = $this->widgetService->processRegistration($gym, [
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
                'iban' => $request->iban,
                'account_holder' => $request->account_holder,
                'sepa_mandate' => $request->boolean('sepa_mandate'),
                'voucher_code' => $request->voucher_code,
                'fitness_goals' => $request->fitness_goals,
                'widget_session' => $sessionId,
            ]);

            // Session-Daten cleanup nach erfolgreicher Registrierung
            $this->cleanupWidgetSession($sessionId);

            // Erfolgs-Response
            return response()->json($result);

        } catch (\Exception $e) {

            // Submission-Lock entfernen bei Fehler
            $this->clearSubmissionLock($sessionId);

            logger()->error('Widget contract creation failed', [
                'gym_id' => $gymId,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'request_data' => $request->except(['iban', 'password'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.',
                'error_code' => 'CREATION_FAILED'
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
     * Session-Daten cleanup
     */
    private function cleanupWidgetSession(string $sessionId): void
    {
        if (!$sessionId) return;

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
    }

    /**
     * Widget Session-Daten setzen
     */
    private function setWidgetSessionData(string $sessionId, string $key, $value): void
    {
        Cache::put(
            "widget_session:{$sessionId}:{$key}",
            $value,
            now()->addMinutes(30)
        );
    }

    /**
     * Widget Session-Daten abrufen
     */
    private function getWidgetSessionData(string $sessionId, string $key)
    {
        return Cache::get("widget_session:{$sessionId}:{$key}");
    }

    /**
     * Prüfe auf doppelte Submission
     */
    private function isDuplicateSubmission(string $sessionId): bool
    {
        return Cache::has("widget_session:{$sessionId}:submission_lock");
    }

    /**
     * Markiere Submission als in Bearbeitung
     */
    private function markSubmissionInProgress(string $sessionId): void
    {
        Cache::put(
            "widget_session:{$sessionId}:submission_lock",
            now()->toISOString(),
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
            'first_name', 'last_name', 'email', 'phone',
            'address', 'address_addition', 'city', 'postal_code', 'country',
            'birth_day', 'birth_month', 'birth_year',
            'salutation', 'fitness_goals', 'voucher_code'
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
            ->whereIn('event_type', ['view', 'plan_selected', 'form_started', 'form_completed', 'registration_completed'])
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
}
