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
        return array_merge(parent::share($request), [
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
    }
}
