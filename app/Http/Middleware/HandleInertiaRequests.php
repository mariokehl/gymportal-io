<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
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
     * Session flash keys that become toasts, with their toast type. Redirect
     * with one of them, e.g. back()->with('success', '…'); 'message' is the
     * legacy key for a success message.
     */
    public const TOAST_FLASH_KEYS = [
        'success' => 'success',
        'message' => 'success',
        'info' => 'info',
        'warning' => 'warning',
        'error' => 'error',
    ];

    public function handle(Request $request, Closure $next)
    {
        $this->flashToasts($request);

        return parent::handle($request, $next);
    }

    /**
     * Hands the flashed messages of the previous request to Inertia's flash
     * data as "toasts". Unlike a shared prop, flash data is sent with exactly
     * one response and never stored in the browser history, so the frontend
     * shows every message once — partial reloads and back navigation do not
     * replay it. Also accepts ->with('flash', ['type' => …, 'message' => …]).
     */
    protected function flashToasts(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $session = $request->session();
        $toasts = [];

        foreach (self::TOAST_FLASH_KEYS as $key => $type) {
            if (is_string($message = $session->get($key)) && $message !== '') {
                $toasts[] = ['type' => $type, 'message' => $message];
            }
        }

        $flash = $session->get('flash');

        if (is_array($flash) && is_string($flash['message'] ?? null)) {
            $type = in_array($flash['type'] ?? null, self::TOAST_FLASH_KEYS, true) ? $flash['type'] : 'success';
            $toasts[] = ['type' => $type, 'message' => $flash['message']];
        }

        if ($toasts !== []) {
            Inertia::flash('toasts', $toasts);
        }
    }

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

                    return [
                        'id' => $user->id,
                        'role_id' => $user->role_id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email, // E-Mail hinzugefügt für Verifizierungsseite
                        'current_gym' => $user->currentGym !== null ? [
                            'id' => $user->currentGym->id,
                            'name' => $user->currentGym->getDisplayName(),
                            'role' => $user->roleInGym($user->currentGym),
                            'can_manage' => $user->canManageGym($user->currentGym),
                            'symbol' => $user->currentGym->getSymbol(),
                        ] : null,
                        // Every gym the user can access: owned gyms plus gyms
                        // they belong to via gym_users. Drives the organization
                        // switcher and its management affordances.
                        'all_gyms' => $user->accessibleGyms()->map(function (Gym $organization) use ($user): array {
                            return [
                                'id' => $organization->id,
                                'name' => $organization->getDisplayName(),
                                'role' => $user->roleInGym($organization),
                                'can_manage' => $user->canManageGym($organization),
                                'symbol' => $organization->getSymbol(),
                            ];
                        })->all(),
                    ];
                },
                'verified' => $request->user()?->hasVerifiedEmail(),
            ],
            'impersonation' => [
                'active' => session()->has('impersonator_id'),
                'impersonator_name' => session('impersonator_name'),
                'impersonated_user_name' => session('impersonated_user_name'),
                'impersonated_user_email' => session('impersonated_user_email'),
            ],
            // Root of the member PWA, so the admin UI can build member-facing
            // links (portal preview, e-mail template previews) without hard-
            // coding the production domain.
            'pwaUrl' => config('app.pwa_url'),
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

                $shared['mollie_configured'] = $gym->hasMollieConfigured();

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

        return $shared;
    }
}
