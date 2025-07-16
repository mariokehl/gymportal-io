<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Gym;
use Carbon\Carbon;

class BillingController extends Controller
{
    public function index()
    {
        $gym = auth()->user()->currentGym;

        // Berechne Testphase
        $trialEndsAt = $gym->trial_ends_at ?: $gym->created_at->addDays(30);
        $isInTrial = now()->lt($trialEndsAt);
        $trialDaysLeft = $isInTrial ? round(now()->diffInDays($trialEndsAt)) : 0;

        // Subscription Status
        $hasActiveSubscription = $gym->subscription_status === 'active';

        // Nächste Zahlung (nur wenn aktive Subscription)
        $nextPayment = null;
        if ($hasActiveSubscription && $gym->subscription_ends_at) {
            $nextPayment = [
                'date' => $gym->subscription_ends_at->format('d.m.Y'),
                'amount' => '29,00'
            ];
        }

        return Inertia::render('Billing/Index', [
            'gym' => $gym,
            'subscription' => [
                'is_active' => $hasActiveSubscription,
                'plan' => $gym->subscription_plan ?? 'SaaS Hosted',
                'status' => $gym->subscription_status,
                'paddle_subscription_id' => $gym->paddle_subscription_id,
                'ends_at' => $gym->subscription_ends_at?->format('d.m.Y'),
            ],
            'trial' => [
                'is_active' => $isInTrial,
                'ends_at' => $trialEndsAt->format('d.m.Y'),
                'days_left' => $trialDaysLeft,
            ],
            'next_payment' => $nextPayment,
            'paddle_token' => config('services.paddle.token'),
            'paddle_environment' => config('services.paddle.environment', 'sandbox'),
        ]);
    }

    public function subscribeToProfessional(Request $request)
    {
        $gym = auth()->user()->currentGym;

        // Paddle Checkout initialisieren
        $checkoutData = [
            'customData' => [
                'gym_id' => $gym->id,
            ],
            'items' => [
                [
                    'priceId' => config('services.paddle.price_id'),
                    'quantity' => 1,
                ]
            ],
            'customer' => [
                'email' => auth()->user()->email,
                'address' => [
                    'countryCode' => $gym->country,
                    'postalCode' => $gym->postal_code,
                ]
            ]
        ];

        return response()->json([
            'success' => true,
            'checkout_data' => $checkoutData,
        ]);
    }

    public function paddleWebhook(Request $request)
    {
        // Paddle Webhook verarbeiten
        $eventType = $request->input('event_type');
        $gymId = $request->input('data.custom_data')['gym_id'] ?? null;

        if (!$gymId) {
            return response('Invalid passthrough data', 400);
        }

        $gym = Gym::find($gymId);
        if (!$gym) {
            return response('Gym not found', 404);
        }

        switch ($eventType) {
            case 'subscription.created':
            case 'subscription.updated':
                $gym->update([
                    'paddle_subscription_id' => $request->input('data.id'),
                    'subscription_status' => 'active',
                    'subscription_plan' => 'SaaS Hosted',
                    'subscription_ends_at' => Carbon::parse($request->input('data.next_billed_at')),
                    'trial_ends_at' => Carbon::parse($request->input('data.current_billing_period.starts_at')),
                ]);
                break;

            case 'subscription.cancelled':
                $gym->update([
                    'subscription_status' => 'cancelled',
                    'subscription_ends_at' => Carbon::parse($request->input('cancellation_effective_date')),
                ]);
                break;

            case 'subscription.payment_success':
                $gym->update([
                    'subscription_status' => 'active',
                    'subscription_ends_at' => Carbon::parse($request->input('next_billed_at')),
                ]);
                break;

            case 'subscription.payment_failed':
                $gym->update([
                    'subscription_status' => 'past_due',
                ]);
                break;
        }

        return response('OK', 200);
    }

    public function cancelSubscription()
    {
        $gym = auth()->user()->currentGym;

        if (!$gym->paddle_subscription_id) {
            return back()->withErrors(['error' => 'Kein aktives Abonnement gefunden.']);
        }

        // Paddle API Call zum Kündigen
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://vendors.paddle.com/api/2.0/subscription/users_cancel',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'vendor_id' => config('services.paddle.vendor_id'),
                'vendor_auth_code' => config('services.paddle.auth_code'),
                'subscription_id' => $gym->paddle_subscription_id,
            ]),
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode === 200) {
            return back()->with('success', 'Abonnement wurde erfolgreich gekündigt.');
        }

        return back()->withErrors(['error' => 'Fehler beim Kündigen des Abonnements.']);
    }
}
