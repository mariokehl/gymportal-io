<?php

namespace App\Services;

use App\Dto\PaymentCreationResult;
use App\Events\MemberRegistered;
use App\Mail\SepaMandateRequiredMail;
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
            $requiresMollieCheckout = $this->requiresMollieCheckout($data['payment_method']);

            try {
                $birthDate = Carbon::parse($data['birth_date']);
            } catch (InvalidFormatException $e) {
                return [
                    'error' => 'Ungültiges Datumsformat',
                    'data' => $data,
                ];
            }

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
                    'registration_ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'registered_at' => now()->toISOString(),
                ],
            ]);

            $plan = MembershipPlan::findOrFail($data['plan_id']);

            $paymentMethod = $this->createPaymentMethod($member, $data);

            $membership = $this->createMembership($member, $plan, $data['payment_method']);

            $registration = WidgetRegistration::where('gym_id', $gym->id)
                ->where('session_id', session()->getId())
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($registration) {
                $registration->update([
                    'member_id' => $member->id,
                    'status' => $requiresMollieCheckout ? 'pending' : 'completed',
                    'completed_at' => $requiresMollieCheckout ? null : Carbon::now(),
                ]);
            }

            if ($requiresMollieCheckout) {
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

            $paymentResult = $this->createPayment($member, $plan, $membership, $paymentMethod);

            $this->trackEvent($gym, 'registration_completed', 'checkout', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'plan_id' => $plan->id,
                'registration_id' => $registration?->id,
                'payment_method' => $data['payment_method'],
            ]);

            DB::commit();

            MemberRegistered::dispatch(
                $member,
                $membership,
                $gym,
                'widget',
                [
                    'payment_method' => $paymentMethod,
                    'session_id' => $data['session_id'] ?? null,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]
            );

            app(MemberService::class)->sendWelcomeEmail($member, $gym);

            if ($paymentMethod && $paymentMethod->requiresSepaMandate()) {
                $this->handleSepaMandate($member, $paymentMethod, $gym);
            }

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

            if ($paymentMethod) {
                $response['payment_method_id'] = $paymentMethod->id;
            }

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

            $this->trackEvent($gym, 'registration_failed', 'form', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Prüft ob die Zahlungsmethode einen Mollie-Checkout benötigt (Weiterleitung)
     */
    private function requiresMollieCheckout(string $paymentMethod): bool
    {
        return str_starts_with($paymentMethod, 'mollie_') && $paymentMethod !== 'mollie_directdebit';
    }

    /**
     * Mollie-Payment erstellen und Checkout-URL generieren
     */
    private function createMolliePayment(Member $member, MembershipPlan $plan, Membership $membership, array $data): array
    {
        $mollieService = app(MollieService::class);
        $gym = $member->gym;

        $mollieCustomer = $mollieService->createCustomer($gym, $member->fullName(), $member->email);

        $mollieService->storeMolliePaymentMethod($member, $data['payment_method'], $mollieCustomer->id, null);

        $amount = $plan->price;
        $description = "1. Mitgliedsbeitrag: {$plan->name}";

        if ($plan->setup_fee > 0) {
            $amount += $plan->setup_fee;
            $description = "Aktivierungsgebühr + " . $description;
        }

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

        WidgetRegistration::where('id', $widgetRegistration->id)
            ->update([
                'payment_data' => [
                    'mollie_customer_id' => $mollieCustomer->id,
                    'mollie_payment_id' => $molliePayment->id
                ],
                'updated_at' => now()
            ]);

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
            $molliePayment = $mollieService->getPayment($gym, $paymentId);

            $localPayment = Payment::where('mollie_payment_id', $paymentId)
                ->where('gym_id', $gym->id)
                ->first();

            if (!$localPayment) {
                throw new \Exception('Payment reference not found');
            }

            $member = $localPayment->member;
            $membership = $localPayment->membership;

            $localPayment->update([
                'mollie_status' => $molliePayment->status,
                'paid_date' => $molliePayment->isPaid() ? now() : null
            ]);

            if ($molliePayment->isPaid()) {
                $member->update(['status' => 'active']);
                $membership->update(['status' => 'active']);

                WidgetRegistration::where('gym_id', $gym->id)
                    ->where('session_id', $sessionId)
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now()
                    ]);

                $mollieService->activateMolliePaymentMethod($gym, $member->id, $localPayment->payment_method);

                app(MemberService::class)->sendWelcomeEmail($member, $gym);

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

        if ($this->requiresMollieCheckout($paymentMethod)) {
            return null;
        }

        switch ($paymentMethod) {
            case 'sepa_direct_debit':
            case 'mollie_directdebit':
                $createdPaymentMethod = PaymentMethod::createSepaPaymentMethod(
                    $member,
                    $data['sepa_mandate_acknowledged'] ?? false,
                    $paymentMethod,
                    $data['iban'] ?? null,
                    $data['account_holder'] ?? null
                );
                if ($data['sepa_mandate_acknowledged']) {
                    $createdPaymentMethod->markSepaMandateAsSigned();
                    $createdPaymentMethod->activateSepaMandate();
                }
                break;

            case 'cash':
            case 'banktransfer':
            case 'invoice':
            case 'standingorder':
                $createdPaymentMethod = PaymentMethod::create([
                    'member_id' => $member->id,
                    'type' => $paymentMethod,
                    'status' => 'active',
                    'is_default' => true,
                ]);
                break;

            default:
                break;
        }

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
        $memberService = app(MemberService::class);

        $membership = $memberService
            ->createMembership(
                $member,
                $plan,
                $this->determineMembershipStatus($paymentMethod, null)
            );

        return $membership;
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
            'sepa_direct_debit', 'mollie_directdebit' => 'pending',
            'banktransfer', 'invoice', 'standingorder' => 'pending',
            'cash' => 'active',
            default => $this->requiresMollieCheckout($paymentMethod) ? 'pending' : 'active',
        };
    }

    /**
     * Mitgliedschaftsstatus basierend auf Zahlungsmethode und PaymentMethod bestimmen
     */
    private function determineMembershipStatus(string $paymentMethod, ?PaymentMethod $paymentMethodModel): string
    {
        return match($paymentMethod) {
            'sepa_direct_debit', 'mollie_directdebit' => 'pending',
            'cash' => 'active',
            'banktransfer', 'invoice', 'standingorder' => 'pending',
            default => $this->requiresMollieCheckout($paymentMethod) ? 'pending' : 'active',
        };
    }

    /**
     * Next Steps basierend auf Zahlungsmethode und PaymentMethod
     */
    private function getNextSteps(string $paymentMethod, Member $member, ?PaymentMethod $paymentMethodModel): array
    {
        if ($this->requiresMollieCheckout($paymentMethod)) {
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
            'mollie_directdebit' => [
                'title' => 'SEPA-Lastschrift über Mollie',
                'description' => 'Ihre SEPA-Lastschrift wurde über Mollie eingerichtet und ist sofort aktiv.',
                'action_required' => false,
                'mandate_reference' => $paymentMethodModel?->sepa_mandate_reference,
                'provider' => 'mollie',
                'info' => [
                    'Die Lastschrift erfolgt automatisch zum Fälligkeitsdatum',
                    'Sie können die Lastschrift jederzeit in Ihrem Banking widerrufen',
                    'Eine Bestätigung erhalten Sie per E-Mail',
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
                'session_id' => request()->header('X-Widget-Session'),
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
                'provider' => $paymentMethod->provider ?? 'standard',
            ];

            if ($paymentMethod->requiresSepaMandate()) {
                $analyticsData['sepa_mandate_reference'] = $paymentMethod->sepa_mandate_reference;
                $analyticsData['sepa_mandate_status'] = $paymentMethod->sepa_mandate_status;
                $analyticsData['sepa_mandate_acknowledged'] = $paymentMethod->sepa_mandate_acknowledged;
                $analyticsData['account_holder'] = $paymentMethod->account_holder;
                $analyticsData['iban'] = substr($paymentMethod->iban ?? '', 0, 8) . '***';
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
        $this->sendSepaMandateEmail($member, $paymentMethod, $gym);
        $this->notifyGymAboutSepaMandate($member, $paymentMethod, $gym);
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
        logger()->info('New SEPA mandate requires processing', [
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'payment_method_id' => $paymentMethod->id,
            'mandate_reference' => $paymentMethod->sepa_mandate_reference,
            'member_name' => $member->full_name,
            'member_email' => $member->email,
            'provider' => $paymentMethod->provider ?? 'standard',
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

        $totalViews = WidgetAnalytics::where('gym_id', $gym->id)
            ->where('event_type', 'view')
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();

        $conversionRate = $totalViews > 0
            ? round(($thisMonthRegistrations / $totalViews) * 100, 2)
            : 0;

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

        $activePlans = $gym->membershipPlans()->where('is_active', true)->count();
        if ($activePlans === 0) {
            $errors[] = 'Mindestens ein aktiver Mitgliedschaftsplan ist erforderlich.';
        }

        if (!$gym->api_key) {
            $errors[] = 'API-Key ist erforderlich.';
        }

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
        if (empty($code)) {
            return ['valid' => true, 'discount' => 0];
        }

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

            $gym->update([
                'widget_enabled' => $config['widget_enabled'] ?? false,
                'widget_settings' => $config['widget_settings'] ?? [],
            ]);

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
