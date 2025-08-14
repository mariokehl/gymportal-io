<?php

namespace App\Services;

use App\DTOs\PaymentCreationResult;
use App\Mail\SepaMandateRequiredMail;
use App\Mail\WelcomeMemberMail;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\WidgetRegistration;
use App\Models\WidgetAnalytics;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Mail;

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
     * Vollständige Registrierung verarbeiten - erweitert um Mollie-Support
     */
    public function processRegistration(Gym $gym, array $data): array
    {
        DB::beginTransaction();

        try {
            // Prüfen ob Mollie-Payment-Method gewählt wurde
            $isMolliePayment = $this->isMolliePaymentMethod($data['payment_method']);

            // Geburtsdatum parsen
            try {
                $birthDate = Carbon::parse($data['birth_date']);
            } catch (InvalidFormatException $e) {
                return [
                    'error' => 'Ungültiges Datumsformat',
                    'data' => $data,
                ];
            }

            // Member erstellen
            $member = Member::create([
                'gym_id' => $gym->id,
                'member_number' => MemberService::generateMemberNumber($gym, 'W'),
                'salutation' => $data['salutation'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'birth_date' => $birthDate ?? null,
                'address' => $data['address'] ?? null,
                'address_addition' => $data['address_addition'] ?? null,
                'city' => $data['city'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? 'DE',
                'voucher_code' => $data['voucher_code'] ?? null,
                'fitness_goals' => $data['fitness_goals'] ?? null,
                'status' => $this->determineMemberStatus($data['payment_method']),
                'joined_date' => now(),
                'registration_source' => 'widget',
                'widget_data' => [
                    'session_id' => $data['widget_session'] ?? null,
                    'payment_method' => $data['payment_method'] ?? null,
                    'sepa_mandate_acknowledged' => $data['sepa_mandate_acknowledged'] ?? false,
                    'registration_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'registered_at' => now()->toISOString(),
                ],
            ]);

            // Plan laden
            $plan = MembershipPlan::findOrFail($data['plan_id']);

            // PaymentMethod erstellen basierend auf gewählter Zahlungsart
            $paymentMethod = $this->createPaymentMethod($member, $data);

            // Membership erstellen
            $membership = $this->createMembership($member, $plan, $data['payment_method']);

            // Widget-Registrierung aktualisieren
            $registration = WidgetRegistration::where('gym_id', $gym->id)
                ->where('session_id', session()->getId())
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($registration) {
                $registration->update([
                    'member_id' => $member->id,
                    'status' => $isMolliePayment ? 'pending' : 'completed',
                    'completed_at' => $isMolliePayment ? null : Carbon::now(),
                ]);
            }

            // Bei Mollie-Zahlungen: Checkout-URL erstellen
            if ($isMolliePayment) {
                $mollieResult = $this->createMolliePayment($member, $plan, $membership, $data);

                DB::commit();

                return [
                    'success' => true,
                    'requires_payment' => true,
                    'session_id' => session()->getId(),
                    'payment_provider' => 'mollie',
                    'checkout_url' => $mollieResult['checkout_url'],
                    'payment_id' => $mollieResult['payment_id'],
                    'member' => [
                        'id' => $member->id,
                        'member_number' => $member->member_number,
                        'first_name' => $member->first_name,
                        'last_name' => $member->last_name,
                        'email' => $member->email,
                        'status' => $member->status,
                    ],
                    'membership' => [
                        'id' => $membership->id,
                        'status' => $membership->status,
                        'start_date' => $membership->start_date->format('d.m.Y'),
                        'payment_method' => $membership->payment_method,
                    ],
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'price' => $plan->price,
                    ],
                    'next_steps' => [
                        'title' => 'Zahlung abschließen',
                        'description' => 'Sie werden zur sicheren Zahlung weitergeleitet.',
                        'action_required' => true,
                        'payment_method' => $data['payment_method'],
                    ]
                ];
            }

            // Standard-Verarbeitung für Nicht-Mollie-Zahlungen
            $paymentResult = $this->createPayment($member, $plan, $membership, $paymentMethod);

            // Analytics-Event tracken
            $this->trackEvent($gym, 'registration_completed', 'checkout', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'plan_id' => $plan->id,
                'registration_id' => $registration?->id,
                'payment_method' => $data['payment_method'],
            ]);

            DB::commit();

            // E-Mails versenden
            $this->sendWelcomeEmail($member, $gym, $plan);

            // SEPA-spezifische Behandlung
            if ($paymentMethod && $paymentMethod->requiresSepaMandate()) {
                $this->handleSepaMandate($member, $paymentMethod, $gym);
            }

            // Response-Daten vorbereiten
            $response = [
                'success' => true,
                'requires_payment' => false,
                'session_id' => session()->getId(),
                'member' => [
                    'id' => $member->id,
                    'member_number' => $member->member_number,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'status' => $member->status,
                ],
                'membership' => [
                    'id' => $membership->id,
                    'status' => $membership->status,
                    'start_date' => $membership->start_date->format('d.m.Y'),
                    'payment_method' => $membership->payment_method,
                ],
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                ],
                'payments' => $paymentResult->toArray(),
                'next_steps' => $this->getNextSteps($data['payment_method'], $member, $paymentMethod),
            ];

            // PaymentMethod ID für Analytics hinzufügen
            if ($paymentMethod) {
                $response['payment_method_id'] = $paymentMethod->id;
            }

            // SEPA-Informationen zur Antwort hinzufügen
            if ($paymentMethod && $paymentMethod->requiresSepaMandate()) {
                $response['sepa_mandate'] = [
                    'reference' => $paymentMethod->sepa_mandate_reference,
                    'status' => $paymentMethod->sepa_mandate_status,
                    'acknowledged_online' => $paymentMethod->sepa_mandate_acknowledged,
                    'next_steps' => [
                        'signature_required' => true,
                        'method' => 'paper',
                        'deadline' => now()->addDays(14)->format('d.m.Y'),
                    ]
                ];
            }

            return $response;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Widget-Registrierung fehlgeschlagen', [
                'gym_id' => $gym->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            // Fehler-Event tracken
            $this->trackEvent($gym, 'registration_failed', 'form', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Prüft ob es sich um eine Mollie-Zahlungsmethode handelt
     */
    private function isMolliePaymentMethod(string $paymentMethod): bool
    {
        return str_starts_with($paymentMethod, 'mollie_');
    }

    /**
     * Mollie-Payment erstellen und Checkout-URL generieren
     */
    private function createMolliePayment(Member $member, MembershipPlan $plan, Membership $membership, array $data): array
    {
        $mollieService = app(MollieService::class);
        $gym = $member->gym;

        // Einrichten der ersten Zahlung
        $mollieCustomer = $mollieService->createCustomer($gym, $member->fullName(), $member->email);

        // Zahlungsart erstellen
        $mollieService->storeMolliePaymentMethod($member, $data['payment_method'], $mollieCustomer->id, null);

        // Betrag berechnen (Setup-Fee + erste Monatsgebühr)
        $amount = $plan->price;
        $description = "1. Mitgliedsbeitrag: {$plan->name}";

        if ($plan->setup_fee > 0) {
            $amount += $plan->setup_fee;
            $description = "Aktivierungsgebühr + " . $description;
        }

        // Mollie-Payment erstellen
        $paymentData = [
            'amount' => number_format($amount, 2, '.', ''),
            'description' => $description,
            'redirectUrl' => route('widget.mollie.return', [
                'gym' => $gym->id,
                'session' => $data['widget_session']
            ]),
            'method' => $data['payment_method'],
            'metadata' => [
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'plan_id' => $plan->id,
                'widget_session' => $data['widget_session'],
                'source' => 'widget'
            ]
        ];
        $molliePayment = $mollieService->createFirstPayment($gym, $mollieCustomer->id, $paymentData);

        $widgetRegistration = WidgetRegistration::where('form_data', 'like', '%"widget_session":"' . $data['widget_session'] . '"%')->latest()->first();

        // Widget-Registrierung für Weiterleitung markieren
        WidgetRegistration::where('id', $widgetRegistration->id)
            ->update([
                'payment_data' => [
                    'mollie_customer_id' => $mollieCustomer->id,
                    'mollie_payment_id' => $molliePayment->id
                ],
                'updated_at' => now()
            ]);

        // Analytics-Event für Mollie-Payment
        $this->trackEvent($gym, 'mollie_payment_created', 'payment', [
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'plan_id' => $plan->id,
            'payment_method' => $data['payment_method'],
            'amount' => $amount,
            'mollie_payment_id' => $molliePayment->id
        ]);

        return [
            'checkout_url' => $molliePayment->getCheckoutUrl(),
            'payment_id' => $molliePayment->id,
            'amount' => $amount
        ];
    }

    /**
     * Mollie-Payment-Return verarbeiten
     */
    public function processMollieReturn(Gym $gym, string $sessionId, string $paymentId): array
    {
        $mollieService = app(MollieService::class);

        try {
            // Mollie-Payment-Status abrufen
            $molliePayment = $mollieService->getPayment($gym, $paymentId);

            // Lokale Payment-Referenz finden
            $localPayment = Payment::where('mollie_payment_id', $paymentId)
                ->where('gym_id', $gym->id)
                ->first();

            if (!$localPayment) {
                throw new \Exception('Payment reference not found');
            }

            $member = $localPayment->member;
            $membership = $localPayment->membership;
            $plan = $localPayment->membership->membershipPlan;

            // Status aktualisieren
            $localPayment->update([
                'mollie_status' => $molliePayment->status,
                'paid_date' => $molliePayment->isPaid() ? now() : null
            ]);

            if ($molliePayment->isPaid()) {
                // Payment erfolgreich - Member und Membership aktivieren
                $member->update(['status' => 'active']);
                $membership->update(['status' => 'active']);

                // Widget-Registrierung als abgeschlossen markieren
                WidgetRegistration::where('gym_id', $gym->id)
                    ->where('session_id', $sessionId)
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now()
                    ]);

                // PaymentMethod aktualisieren
                $mollieService->activateMolliePaymentMethod($gym, $member->id, $localPayment->payment_method);

                // Welcome-Email senden
                $this->sendWelcomeEmail($member, $gym, $plan);

                // Analytics
                $this->trackEvent($gym, 'mollie_payment_completed', 'payment_success', [
                    'member_id' => $member->id,
                    'membership_id' => $membership->id,
                    'payment_method' => $localPayment->method,
                    'amount' => $localPayment->amount,
                    'mollie_payment_id' => $paymentId
                ]);

                return [
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Zahlung erfolgreich! Ihre Mitgliedschaft ist jetzt aktiv.',
                    'member' => [
                        'id' => $member->id,
                        'member_number' => $member->member_number,
                        'status' => $member->status
                    ],
                    'membership' => [
                        'id' => $membership->id,
                        'status' => $membership->status
                    ],
                    'next_steps' => [
                        'title' => 'Willkommen im Studio!',
                        'description' => 'Sie erhalten eine Bestätigungs-E-Mail.',
                        'action_required' => false
                    ]
                ];

            } elseif ($molliePayment->isCanceled() || $molliePayment->isExpired()) {
                // Payment abgebrochen/abgelaufen
                $this->trackEvent($gym, 'mollie_payment_failed', 'payment_failed', [
                    'member_id' => $member->id,
                    'reason' => $molliePayment->status,
                    'mollie_payment_id' => $paymentId
                ]);

                return [
                    'success' => false,
                    'status' => $molliePayment->status,
                    'message' => 'Die Zahlung wurde abgebrochen oder ist abgelaufen.',
                    'retry_possible' => true
                ];

            } else {
                // Payment noch pending
                return [
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Ihre Zahlung wird noch verarbeitet.',
                    'check_again' => true
                ];
            }

        } catch (\Exception $e) {
            Log::error('Mollie payment return processing failed', [
                'gym_id' => $gym->id,
                'session_id' => $sessionId,
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => 'Fehler bei der Zahlungsverarbeitung.',
                'retry_possible' => true
            ];
        }
    }

    /**
     * PaymentMethod basierend auf Zahlungsart erstellen
     */
    private function createPaymentMethod(Member $member, array $data): ?PaymentMethod
    {
        $paymentMethod = $data['payment_method'];
        $createdPaymentMethod = null;

        // Mollie-Payments werden später durch Webhook/Return erstellt
        if ($this->isMolliePaymentMethod($paymentMethod)) {
            return null;
        }

        switch ($paymentMethod) {
            case 'sepa_direct_debit':
                $createdPaymentMethod = PaymentMethod::createSepaPaymentMethod(
                    $member,
                    $data['sepa_mandate_acknowledged'] ?? false
                );
                break;

            case 'cash':
            case 'banktransfer':
            case 'invoice':
            case 'standingorder':
                // Standard-Zahlungsmethoden erstellen
                $createdPaymentMethod = PaymentMethod::create([
                    'member_id' => $member->id,
                    'type' => $paymentMethod,
                    'status' => 'active',
                    'is_default' => true,
                ]);
                break;

            default:
                // Unbekannte Zahlungsmethoden
                break;
        }

        // Analytics tracking für erstellte PaymentMethod
        if ($createdPaymentMethod) {
            $this->trackPaymentMethodCreation($data['widget_session'], $createdPaymentMethod, $member->gym);
        }

        return $createdPaymentMethod;
    }

    /**
     * Mitgliedschaft erstellen
     */
    private function createMembership(Member $member, MembershipPlan $plan, string $paymentMethod): Membership
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
                'status' => $this->determineMembershipStatus($paymentMethod, null),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_method' => $paymentMethod,
                'monthly_fee' => $plan->price,
                'setup_fee' => $plan->setup_fee ?? 0,
                'commitment_months' => $plan->commitment_months ?? 0,
                'cancellation_period_days' => $plan->cancellation_period_days ?? 30,
        ]);
    }

    private function createPayment(
        Member $member,
        MembershipPlan $plan,
        Membership $membership,
        ?PaymentMethod $paymentMethod = null
    ): PaymentCreationResult {
        $paymentService = app(PaymentService::class);

        $setupPayment = null;
        if ($plan->setup_fee > 0) {
            $setupPayment = $paymentService->createSetupFeePayment(
                $member,
                $membership,
                $paymentMethod
            );
        }

        $initialPayment = $paymentService->createPendingPayment(
            $member,
            $membership,
            $paymentMethod
        );

        return new PaymentCreationResult(
            setupPayment: $setupPayment,
            initialPayment: $initialPayment
        );
    }

    /**
     * Member-Status basierend auf Zahlungsmethode bestimmen
     */
    private function determineMemberStatus(string $paymentMethod): string
    {
        return match($paymentMethod) {
            'sepa_direct_debit' => 'pending', // Wartet auf SEPA-Mandat
            'banktransfer', 'invoice', 'standingorder' => 'pending', // Wartet auf Zahlung
            'cash' => 'active', // Sofort aktiv
            default => $this->isMolliePaymentMethod($paymentMethod) ? 'pending' : 'active',
        };
    }

    /**
     * Mitgliedschaftsstatus basierend auf Zahlungsmethode und PaymentMethod bestimmen
     */
    private function determineMembershipStatus(string $paymentMethod, ?PaymentMethod $paymentMethodModel): string
    {
        return match($paymentMethod) {
            'sepa_direct_debit' => 'pending', // Wartet auf SEPA-Mandat
            'cash' => 'active', // Sofort aktiv
            'banktransfer', 'invoice', 'standingorder' => 'pending', // Wartet auf Zahlung
            default => $this->isMolliePaymentMethod($paymentMethod) ? 'pending' : 'active',
        };
    }

    /**
     * Next Steps basierend auf Zahlungsmethode und PaymentMethod
     */
    private function getNextSteps(string $paymentMethod, Member $member, ?PaymentMethod $paymentMethodModel): array
    {
        if ($this->isMolliePaymentMethod($paymentMethod)) {
            return [
                'title' => 'Zahlung abschließen',
                'description' => 'Schließen Sie die Zahlung über Mollie ab.',
                'action_required' => true,
                'payment_provider' => 'mollie',
                'payment_method' => $paymentMethod
            ];
        }

        return match($paymentMethod) {
            'sepa_direct_debit' => [
                'title' => 'SEPA-Lastschriftmandat unterschreiben',
                'description' => 'Sie erhalten in Kürze eine E-Mail mit dem SEPA-Lastschriftmandat.',
                'action_required' => true,
                'deadline' => now()->addDays(14)->format('d.m.Y'),
                'mandate_reference' => $paymentMethodModel?->sepa_mandate_reference,
                'steps' => [
                    'E-Mail mit SEPA-Formular prüfen',
                    'Formular ausdrucken und unterschreiben',
                    'Unterschriebenes Mandat an das Studio senden',
                ]
            ],
            'cash' => [
                'title' => 'Zahlung vor Ort',
                'description' => 'Ihre Mitgliedschaft ist sofort aktiv. Zahlung erfolgt beim nächsten Besuch.',
                'action_required' => false,
            ],
            'banktransfer' => [
                'title' => 'Überweisung tätigen',
                'description' => 'Sie erhalten die Bankverbindung per E-Mail.',
                'action_required' => true,
                'deadline' => now()->addDays(7)->format('d.m.Y'),
            ],
            'invoice' => [
                'title' => 'Rechnung abwarten',
                'description' => 'Sie erhalten eine Rechnung per E-Mail.',
                'action_required' => false,
            ],
            'standingorder' => [
                'title' => 'Dauerauftrag einrichten',
                'description' => 'Richten Sie einen Dauerauftrag mit den mitgeteilten Daten ein.',
                'action_required' => true,
                'deadline' => now()->addDays(30)->format('d.m.Y'),
            ],
            default => [
                'title' => 'Willkommen!',
                'description' => 'Ihre Registrierung war erfolgreich.',
                'action_required' => false,
            ]
        };
    }

    // ... Rest der ursprünglichen Methoden bleibt unverändert ...

    /**
     * Analytics-Event tracken
     */
    public function trackEvent(Gym $gym, string $eventType, ?string $step = null, array $data = []): void
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
     * PaymentMethod Analytics tracken
     */
    private function trackPaymentMethodCreation(string $sessionId, PaymentMethod $paymentMethod, Gym $gym): void
    {
        try {
            $analyticsData = [
                'payment_method_id' => $paymentMethod->id,
                'type' => $paymentMethod->type,
                'member_id' => $paymentMethod->member_id,
            ];

            // SEPA-spezifische Analytics
            if ($paymentMethod->requiresSepaMandate()) {
                $analyticsData['sepa_mandate_reference'] = $paymentMethod->sepa_mandate_reference;
                $analyticsData['sepa_mandate_status'] = $paymentMethod->sepa_mandate_status;
                $analyticsData['sepa_mandate_acknowledged'] = $paymentMethod->sepa_mandate_acknowledged;
            }

            WidgetAnalytics::create([
                'gym_id' => $gym->id,
                'event_type' => 'payment_method_created',
                'step' => 'contract_creation',
                'data' => $analyticsData,
                'session_id' => $sessionId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Analytics-Fehler nicht weiterleiten
            logger()->warning('Payment method analytics tracking failed', [
                'error' => $e->getMessage(),
                'payment_method_id' => $paymentMethod->id
            ]);
        }
    }

    /**
     * SEPA-Lastschriftmandat behandeln
     */
    private function handleSepaMandate(Member $member, PaymentMethod $paymentMethod, Gym $gym): void
    {
        // E-Mail mit SEPA-Informationen senden
        $this->sendSepaMandateEmail($member, $paymentMethod, $gym);

        // Interne Benachrichtigung an Gym-Team
        $this->notifyGymAboutSepaMandate($member, $paymentMethod, $gym);
    }

    /**
     * Welcome E-Mail senden
     */
    public function sendWelcomeEmail(Member $member, Gym $gym, MembershipPlan $plan): void
    {
        try {
            Mail::to($member->email)->send(new WelcomeMemberMail($member, $gym, $plan));
        } catch (\Exception $e) {
            logger()->error('Failed to send welcome email', [
                'member_id' => $member->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * SEPA-Mandat E-Mail senden
     */
    private function sendSepaMandateEmail(Member $member, PaymentMethod $paymentMethod, Gym $gym): void
    {
        try {
            Mail::to($member->email)->send(new SepaMandateRequiredMail($member, $paymentMethod, $gym));
        } catch (\Exception $e) {
            logger()->error('Failed to send SEPA mandate email', [
                'member_id' => $member->id,
                'payment_method_id' => $paymentMethod->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gym über neues SEPA-Mandat benachrichtigen
     */
    private function notifyGymAboutSepaMandate(Member $member, PaymentMethod $paymentMethod, Gym $gym): void
    {
        // Hier könnte eine interne Benachrichtigung oder Slack-Nachricht gesendet werden
        logger()->info('New SEPA mandate requires processing', [
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'mandate_reference' => $paymentMethod->sepa_mandate_reference,
            'member_name' => $member->full_name,
            'member_email' => $member->email,
        ]);
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

    // Weitere nicht mehr verwendete Methoden bleiben für Kompatibilität...

    /**
     * Widget-Statistiken abrufen
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
     * Widget-Konfiguration validieren
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
     */
    public function validatePlanSelection(Gym $gym, int $planId): bool
    {
        return $gym->membershipPlans()
            ->where('id', $planId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Gutschein-Code validieren
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
     *
     * @deprecated Diese Funktion wird nicht mehr verwendet.
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
