<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\Request;
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
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'current_gym' => $user->currentGym !== null ? [
                            'id' => $user->currentGym->id,
                            'name' => $user->currentGym->name,
                        ] : null,
                    ], array_filter([
                        'all_gyms' => $user->ownedGyms->map(function (Gym $organization): array {
                            return [
                                'id' => $organization->id,
                                'name' => $organization->name,
                            ];
                        })->all(),
                    ]));
                },
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);

        // Subscription Status für alle authentifizierten Benutzer
        if ($request->user()) {
            $gym = $request->user()->currentGym;

            if ($gym) {
                $trialEndsAt = $gym->trial_ends_at ?: $gym->created_at->addDays(30);
                $isInTrial = now()->lt($trialEndsAt);
                $trialDaysLeft = $isInTrial ? now()->diffInDays($trialEndsAt) : 0;

                $hasActiveSubscription = $gym->subscription_status === 'active' &&
                                        $gym->subscription_ends_at &&
                                        $gym->subscription_ends_at->gt(now());

                $shared['subscription_status'] = [
                    'trial' => [
                        'is_active' => $isInTrial,
                        'ends_at' => $trialEndsAt->format('d.m.Y'),
                        'days_left' => round($trialDaysLeft),
                    ],
                    'subscription' => [
                        'is_active' => $hasActiveSubscription,
                        'plan' => $gym->subscription_plan ?? 'SaaS Hosted',
                        'status' => $gym->subscription_status,
                        'ends_at' => $gym->subscription_ends_at?->format('d.m.Y'),
                    ],
                    'can_access_premium' => $isInTrial || $hasActiveSubscription,
                ];
            }
        }

        return $shared;
    }
}
