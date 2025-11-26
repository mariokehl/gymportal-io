<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shared = array_merge(parent::share($request), [
            'auth' => [
                'user' => function () use ($request): array {
                    /** @var User|null $user */
                    $user = $request->user();

                    if ($user === null) {
                        return [];
                    }

                    return array_merge([
                        'id' => $user->id,
                        'role_id' => $user->role_id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email, // E-Mail hinzugefügt für Verifizierungsseite
                        'current_gym' => $user->currentGym !== null ? [
                            'id' => $user->currentGym->id,
                            'name' => $user->currentGym->getDisplayName(),
                        ] : null,
                    ], array_filter([
                        'all_gyms' => $user->ownedGyms->map(function (Gym $organization): array {
                            return [
                                'id' => $organization->id,
                                'name' => $organization->getDisplayName(),
                            ];
                        })->all(),
                    ]));
                },
                'verified' => $request->user()?->hasVerifiedEmail(),
            ],
            'impersonation' => [
                'active' => session()->has('impersonator_id'),
                'impersonator_name' => session('impersonator_name'),
                'impersonated_user_name' => session('impersonated_user_name'),
                'impersonated_user_email' => session('impersonated_user_email'),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'chatwoot' => [
                'enabled' => config('chatwoot.enabled'),
                'token' => config('chatwoot.website_token'),
                'baseUrl' => config('chatwoot.base_url'),
                'identityHash' => function () use ($request) {
                    $user = $request->user();
                    $secret = config('chatwoot.identity_validation_secret');

                    if ($user && $secret) {
                        return hash_hmac('sha256', (string) $user->id, $secret);
                    }

                    return null;
                },
            ],
        ]);

        // Subscription Status für alle authentifizierten Benutzer
        if ($request->user()) {
            /** @var Gym $gym */
            $gym = $request->user()->currentGym;

            if ($gym) {
                $trialEndsAt = $gym->trial_ends_at ?: $gym->created_at->addDays(30);
                $isInTrial = now()->lt($trialEndsAt);
                $trialDaysLeft = $isInTrial ? now()->diffInDays($trialEndsAt) : 0;

                $shared['subscription_status'] = [
                    'trial' => [
                        'is_active' => $isInTrial,
                        'ends_at' => $trialEndsAt->format('d.m.Y'),
                        'days_left' => round($trialDaysLeft),
                    ],
                    'subscription' => [
                        'is_active' => $gym->hasActiveSubscription(),
                        'plan' => $gym->subscription_plan ?? 'SaaS Hosted',
                        'status' => $gym->subscription_status,
                        'ends_at' => $gym->subscription_ends_at?->format('d.m.Y'),
                    ],
                    'can_access_premium' => $isInTrial || $gym->hasActiveSubscription(),
                ];
            }
        }

        // App info + traducciones compartidas con Inertia (incluye members)
        $shared['app'] = [
            'locale'          => app()->getLocale(),
            'fallback_locale' => config('app.fallback_locale'),
            'translations'    => [
                'nav'       => Lang::get('nav'),
                'dashboard' => Lang::get('dashboard'),
                'members'   => Lang::get('members'),
            ],
        ];

        return $shared;
    }
}

