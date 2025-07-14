<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\WidgetRegistration;
use App\Models\WidgetAnalytics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

class WidgetService
{
    /**
     * Widget-Registrierung initialisieren
     */
    public function initializeRegistration(Gym $gym, array $data): WidgetRegistration
    {
        $registration = WidgetRegistration::create([
            'gym_id' => $gym->id,
            'membership_plan_id' => $data['plan_id'],
            'session_id' => $data['session_id'] ?? session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referrer' => $data['referrer'] ?? null,
            'form_data' => $data,
            'status' => 'pending',
            'started_at' => Carbon::now(),
        ]);

        // Analytics-Event tracken
        $this->trackEvent($gym, 'registration_started', 'form', [
            'plan_id' => $data['plan_id'],
            'registration_id' => $registration->id,
        ]);

        return $registration;
    }

    /**
     * Vollständige Registrierung verarbeiten
     */
    public function processRegistration(Gym $gym, array $memberData): array
    {
        DB::beginTransaction();

        try {
            // Mitgliedschaftsplan laden
            $plan = MembershipPlan::where('gym_id', $gym->id)
                ->where('id', $memberData['plan_id'])
                ->firstOrFail();

            // Mitgliedsnummer generieren
            $memberNumber = $this->generateMemberNumber($gym);

            // Geburtsdatum parsen
            try {
                $birthDate = Carbon::parse($memberData['birth_date']);
            } catch (InvalidFormatException $e) {
                return [
                    'error' => 'Ungültiges Datumsformat',
                    'data' => $memberData,
                ];
            }

            // Mitglied erstellen
            $member = Member::create([
                'gym_id' => $gym->id,
                'member_number' => $memberNumber,
                'salutation' => $memberData['salutation'] ?? null,
                'first_name' => $memberData['first_name'],
                'last_name' => $memberData['last_name'],
                'email' => $memberData['email'],
                'phone' => $memberData['phone'],
                'birth_date' => $birthDate,
                'address' => $memberData['address'] ?? null,
                'address_addition' => $memberData['address_addition'] ?? null,
                'city' => $memberData['city'] ?? null,
                'postal_code' => $memberData['postal_code'] ?? null,
                'country' => $memberData['country'] ?? 'DE',
                'iban' => $memberData['iban'] ?? null,
                'account_holder' => $memberData['account_holder'] ?? null,
                'sepa_mandate_accepted' => $memberData['sepa_mandate'] ?? false,
                'sepa_mandate_date' => $memberData['sepa_mandate'] ? Carbon::now() : null,
                'voucher_code' => $memberData['voucher_code'] ?? null,
                'fitness_goals' => $memberData['fitness_goals'] ?? null,
                'status' => 'active',
                'joined_date' => Carbon::now(),
                'registration_source' => 'widget',
                'widget_data' => $memberData,
                'notes' => 'Registrierung über Widget am ' . Carbon::now()->format('d.m.Y H:i'),
            ]);

            // Mitgliedschaft erstellen
            $membership = $this->createMembership($member, $plan);

            // Widget-Registrierung aktualisieren
            $registration = WidgetRegistration::where('gym_id', $gym->id)
                ->where('session_id', session()->getId())
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($registration) {
                $registration->update([
                    'member_id' => $member->id,
                    'status' => 'completed',
                    'completed_at' => Carbon::now(),
                ]);
            }

            // Analytics-Event tracken
            $this->trackEvent($gym, 'registration_completed', 'checkout', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'plan_id' => $plan->id,
                'registration_id' => $registration?->id,
            ]);

            DB::commit();

            // Willkommens-E-Mail senden (optional)
            $this->sendWelcomeEmail($member, $gym);

            return [
                'success' => true,
                'member' => $member,
                'membership' => $membership,
                'plan' => $plan,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Widget-Registrierung fehlgeschlagen', [
                'gym_id' => $gym->id,
                'error' => $e->getMessage(),
                'data' => $memberData,
            ]);

            // Fehler-Event tracken
            $this->trackEvent($gym, 'registration_failed', 'form', [
                'error' => $e->getMessage(),
                'data' => $memberData,
            ]);

            throw $e;
        }
    }

    /**
     * Mitgliedschaft erstellen
     */
    private function createMembership(Member $member, MembershipPlan $plan): Membership
    {
        $startDate = Carbon::now();

        // Probezeit berücksichtigen
        if ($plan->trial_period_days > 0) {
            $trialEndDate = $startDate->copy()->addDays($plan->trial_period_days);
            $endDate = $plan->commitment_months
                ? $trialEndDate->copy()->addMonths($plan->commitment_months)
                : null;
        } else {
            $endDate = $plan->commitment_months
                ? $startDate->copy()->addMonths($plan->commitment_months)
                : null;
        }

        return Membership::create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
        ]);
    }

    /**
     * Mitgliedsnummer generieren
     */
    private function generateMemberNumber(Gym $gym): string
    {
        $prefix = 'W' . str_pad($gym->id, 3, '0', STR_PAD_LEFT);
        $year = date('y');
        $lastNumber = Member::withTrashed()
            ->where('gym_id', $gym->id)
            ->where('member_number', 'like', $prefix . $year . '%')
            ->orderBy('member_number', 'desc')
            ->value('member_number');

        if ($lastNumber) {
            $lastSequence = intval(substr($lastNumber, -4));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix . $year . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Analytics-Event tracken
     */
    public function trackEvent(Gym $gym, string $eventType, string $step = null, array $data = []): void
    {
        try {
            WidgetAnalytics::create([
                'gym_id' => $gym->id,
                'event_type' => $eventType,
                'step' => $step,
                'data' => $data,
                'session_id' => session()->getId(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'referrer' => request()->header('referer'),
                'created_at' => Carbon::now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Widget-Analytics-Event fehlgeschlagen', [
                'gym_id' => $gym->id,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Widget-Statistiken abrufen
     */
    public function getWidgetStats(Gym $gym): array
    {
        $totalRegistrations = Member::where('gym_id', $gym->id)
            ->where('registration_source', 'widget')
            ->count();

        $thisMonthRegistrations = Member::where('gym_id', $gym->id)
            ->where('registration_source', 'widget')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        // Conversion Rate berechnen
        $totalViews = WidgetAnalytics::where('gym_id', $gym->id)
            ->where('event_type', 'view')
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();

        $conversionRate = $totalViews > 0
            ? round(($thisMonthRegistrations / $totalViews) * 100, 2)
            : 0;

        // Beliebtesten Plan ermitteln
        $popularPlan = DB::table('members')
            ->join('memberships', 'members.id', '=', 'memberships.member_id')
            ->join('membership_plans', 'memberships.membership_plan_id', '=', 'membership_plans.id')
            ->where('members.gym_id', $gym->id)
            ->where('members.registration_source', 'widget')
            ->groupBy('membership_plans.id', 'membership_plans.name')
            ->orderBy('count', 'desc')
            ->selectRaw('membership_plans.name, COUNT(*) as count')
            ->first();

        return [
            'total_registrations' => $totalRegistrations,
            'registrations_this_month' => $thisMonthRegistrations,
            'conversion_rate' => $conversionRate,
            'popular_plan' => $popularPlan->name ?? 'N/A',
        ];
    }

    /**
     * Willkommens-E-Mail senden
     */
    private function sendWelcomeEmail(Member $member, Gym $gym): void
    {
        try {
            // Hier würde die E-Mail-Logik implementiert werden
            // Beispiel mit Laravel Mail:
            // Mail::to($member->email)->send(new WelcomeMail($member, $gym));

            Log::info('Willkommens-E-Mail gesendet', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'email' => $member->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Willkommens-E-Mail fehlgeschlagen', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Widget-Konfiguration validieren
     */
    public function validateWidgetConfig(Gym $gym): array
    {
        $errors = [];

        // Mindestens ein aktiver Plan erforderlich
        $activePlans = $gym->membershipPlans()->where('is_active', true)->count();
        if ($activePlans === 0) {
            $errors[] = 'Mindestens ein aktiver Mitgliedschaftsplan ist erforderlich.';
        }

        // API-Key erforderlich
        if (!$gym->api_key) {
            $errors[] = 'API-Key ist erforderlich.';
        }

        // Widget-Einstellungen validieren
        $settings = $gym->widget_settings;

        if (empty($settings['texts']['title'])) {
            $errors[] = 'Widget-Titel ist erforderlich.';
        }

        if (empty($settings['colors']['primary'])) {
            $errors[] = 'Primärfarbe ist erforderlich.';
        }

        return $errors;
    }

    /**
     * Widget-Vorschau generieren
     */
    public function generatePreview(Gym $gym): string
    {
        $plans = $gym->membershipPlans()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        return view('widget.preview', compact('gym', 'plans'))->render();
    }

    /**
     * Plan-Auswahl validieren
     */
    public function validatePlanSelection(Gym $gym, int $planId): bool
    {
        return $gym->membershipPlans()
            ->where('id', $planId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * E-Mail-Adresse validieren (Duplikat-Check)
     */
    public function validateEmail(Gym $gym, string $email): array
    {
        $existingMember = Member::where('gym_id', $gym->id)
            ->where('email', $email)
            ->first();

        if ($existingMember) {
            return [
                'valid' => false,
                'message' => 'Diese E-Mail-Adresse ist bereits registriert.',
                'existing_member' => $existingMember
            ];
        }

        return ['valid' => true];
    }

    /**
     * Gutschein-Code validieren
     */
    public function validateVoucherCode(Gym $gym, string $code): array
    {
        // Hier würde die Gutschein-Validierung implementiert werden
        // Beispiel-Implementierung:

        if (empty($code)) {
            return ['valid' => true, 'discount' => 0];
        }

        // Einfache Validierung - in der Praxis würde hier eine Voucher-Tabelle abgefragt
        $validCodes = [
            'WELCOME2024' => ['discount' => 10, 'type' => 'percent'],
            'STUDENT' => ['discount' => 5, 'type' => 'euro'],
            'FRIEND' => ['discount' => 15, 'type' => 'percent'],
        ];

        if (isset($validCodes[$code])) {
            return [
                'valid' => true,
                'discount' => $validCodes[$code]['discount'],
                'type' => $validCodes[$code]['type'],
                'message' => 'Gutschein erfolgreich eingelöst!'
            ];
        }

        return [
            'valid' => false,
            'message' => 'Ungültiger Gutschein-Code.'
        ];
    }

    /**
     * SEPA-Mandate validieren
     */
    public function validateIban(string $iban): array
    {
        // Einfache IBAN-Validierung
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (strlen($iban) < 15 || strlen($iban) > 34) {
            return [
                'valid' => false,
                'message' => 'IBAN hat eine ungültige Länge.'
            ];
        }

        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/', $iban)) {
            return [
                'valid' => false,
                'message' => 'IBAN hat ein ungültiges Format.'
            ];
        }

        // Für eine vollständige Validierung würde hier der MOD-97-Algorithmus implementiert
        return ['valid' => true];
    }

    /**
     * Widget-Performance-Metriken
     */
    public function getPerformanceMetrics(Gym $gym, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $metrics = [
            'page_views' => WidgetAnalytics::where('gym_id', $gym->id)
                ->where('event_type', 'view')
                ->where('created_at', '>=', $startDate)
                ->count(),

            'plan_selections' => WidgetAnalytics::where('gym_id', $gym->id)
                ->where('event_type', 'plan_selected')
                ->where('created_at', '>=', $startDate)
                ->count(),

            'form_starts' => WidgetAnalytics::where('gym_id', $gym->id)
                ->where('event_type', 'form_started')
                ->where('created_at', '>=', $startDate)
                ->count(),

            'form_completions' => WidgetAnalytics::where('gym_id', $gym->id)
                ->where('event_type', 'form_completed')
                ->where('created_at', '>=', $startDate)
                ->count(),

            'registrations' => WidgetAnalytics::where('gym_id', $gym->id)
                ->where('event_type', 'registration_completed')
                ->where('created_at', '>=', $startDate)
                ->count(),
        ];

        // Conversion-Rates berechnen
        $metrics['plan_to_form_rate'] = $metrics['plan_selections'] > 0
            ? round(($metrics['form_starts'] / $metrics['plan_selections']) * 100, 2)
            : 0;

        $metrics['form_completion_rate'] = $metrics['form_starts'] > 0
            ? round(($metrics['form_completions'] / $metrics['form_starts']) * 100, 2)
            : 0;

        $metrics['overall_conversion_rate'] = $metrics['page_views'] > 0
            ? round(($metrics['registrations'] / $metrics['page_views']) * 100, 2)
            : 0;

        return $metrics;
    }

    /**
     * Widget-Konfiguration exportieren
     */
    public function exportConfig(Gym $gym): array
    {
        return [
            'gym_id' => $gym->id,
            'gym_name' => $gym->name,
            'widget_enabled' => $gym->widget_enabled,
            'widget_settings' => $gym->widget_settings,
            'membership_plans' => $gym->membershipPlans()
                ->where('is_active', true)
                ->select(['id', 'name', 'description', 'price', 'billing_cycle', 'features'])
                ->get()
                ->toArray(),
            'export_date' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Widget-Konfiguration importieren
     */
    public function importConfig(Gym $gym, array $config): bool
    {
        try {
            DB::beginTransaction();

            // Widget-Einstellungen aktualisieren
            $gym->update([
                'widget_enabled' => $config['widget_enabled'] ?? false,
                'widget_settings' => $config['widget_settings'] ?? [],
            ]);

            // Optional: Membership-Pläne aktualisieren
            if (isset($config['membership_plans'])) {
                foreach ($config['membership_plans'] as $planData) {
                    $plan = $gym->membershipPlans()->find($planData['id']);
                    if ($plan) {
                        $plan->update($planData);
                    }
                }
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Widget-Konfiguration Import fehlgeschlagen', [
                'gym_id' => $gym->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Widget-Logs abrufen
     */
    public function getWidgetLogs(Gym $gym, int $limit = 100): array
    {
        $registrations = WidgetRegistration::where('gym_id', $gym->id)
            ->with(['member', 'membershipPlan'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $analytics = WidgetAnalytics::where('gym_id', $gym->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return [
            'registrations' => $registrations,
            'analytics' => $analytics,
        ];
    }

    /**
     * Widget-Health-Check
     */
    public function healthCheck(Gym $gym): array
    {
        $checks = [
            'widget_enabled' => $gym->widget_enabled,
            'api_key_present' => !empty($gym->api_key),
            'active_plans' => $gym->membershipPlans()->where('is_active', true)->count() > 0,
            'recent_activity' => WidgetAnalytics::where('gym_id', $gym->id)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->exists(),
        ];

        $checks['overall_health'] = array_sum($checks) === count($checks);

        return $checks;
    }
}
