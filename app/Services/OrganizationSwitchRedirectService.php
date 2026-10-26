<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Decides where an organization switch lands.
 *
 * Every input (target id, return path) is user controlled, so the redirect is
 * always rebuilt from a named route — the raw path is never echoed back, which
 * rules out open redirects and cross-tenant record links.
 */
class OrganizationSwitchRedirectService
{
    /**
     * Records the "Standort wechseln" dialog may open in the target gym.
     */
    public const TARGETS = [
        'member' => ['route' => 'members.show', 'model' => Member::class],
        'contract' => ['route' => 'contracts.edit', 'model' => MembershipPlan::class],
    ];

    /**
     * Pages that survive a switch, mapped to the GymPolicy ability the user
     * needs in the target gym. Mirrors what the sidebar offers per role.
     */
    private const PRESERVABLE_ROUTES = [
        'dashboard' => 'view',
        'members.index' => 'view',
        'members.create' => 'view',
        'contracts.index' => 'view',
        'contracts.create' => 'view',
        'contracts.addons.index' => 'manage',
        'contracts.addons.create' => 'manage',
        'finances.index' => 'manage',
        'notifications.index' => 'manage',
        'access-control.index' => 'view',
        'blocklist.index' => 'manage',
        'data-transfer.index' => 'manage',
        'settings.index' => 'manage',
        'billing.index' => 'manage',
        'profile.index' => 'view',
    ];

    /**
     * Query parameters carried over to the target page (e.g. the active tab).
     * Values are restricted to plain identifiers.
     */
    private const PRESERVABLE_QUERY = ['tab'];

    public function __construct(private readonly Router $router) {}

    /**
     * @param  array{target?: string|null, target_id?: int|null, return_to?: string|null}  $validated
     */
    public function resolve(User $user, Gym $gym, array $validated): string
    {
        if (! empty($validated['target'])) {
            return $this->targetUrl($gym, $validated['target'], (int) $validated['target_id']);
        }

        if (! empty($validated['return_to'])) {
            return $this->returnUrl($user, $gym, $validated['return_to']);
        }

        return route('dashboard');
    }

    /**
     * Falls back to the dashboard whenever the requested record does not exist
     * in the gym just switched into.
     */
    private function targetUrl(Gym $gym, string $target, int $targetId): string
    {
        $config = self::TARGETS[$target];

        $belongsToGym = $config['model']::whereKey($targetId)
            ->where('gym_id', $gym->id)
            ->exists();

        return $belongsToGym
            ? route($config['route'], $targetId)
            : route('dashboard');
    }

    /**
     * Keeps the page the user was on. Record pages (e.g. a member profile)
     * point at the previous gym's data, so they fall back to their list.
     */
    private function returnUrl(User $user, Gym $gym, string $returnTo): string
    {
        $parts = parse_url($returnTo);

        // Only same-origin, path-only input is considered.
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || ! isset($parts['path'])) {
            return route('dashboard');
        }

        $matched = $this->matchRoute($parts['path']);

        if ($matched === null) {
            return route('dashboard');
        }

        $hasParameters = $matched['has_parameters'];
        $name = $hasParameters ? $this->listRouteFor($matched['name']) : $matched['name'];

        if ($name === null || ! $this->mayOpen($user, $gym, $name)) {
            return route('dashboard');
        }

        // The query of a record page belongs to that record; drop it.
        $query = $hasParameters ? [] : $this->preservedQuery($parts['query'] ?? '');

        return route($name, $query);
    }

    /**
     * @return array{name: string, has_parameters: bool}|null
     */
    private function matchRoute(string $path): ?array
    {
        try {
            $route = $this->router->getRoutes()->match(Request::create($path, 'GET'));
        } catch (HttpException) {
            return null;
        }

        $name = $route->getName();

        if ($name === null) {
            return null;
        }

        return ['name' => $name, 'has_parameters' => $route->parameterNames() !== []];
    }

    /**
     * members.show → members.index, contracts.addons.edit → contracts.addons.index.
     */
    private function listRouteFor(string $name): ?string
    {
        $prefix = substr($name, 0, (int) strrpos($name, '.'));

        return $prefix === '' ? null : $prefix.'.index';
    }

    private function mayOpen(User $user, Gym $gym, string $name): bool
    {
        $ability = self::PRESERVABLE_ROUTES[$name] ?? null;

        return $ability !== null && $user->can($ability, $gym);
    }

    /**
     * @return array<string, string>
     */
    private function preservedQuery(string $query): array
    {
        parse_str($query, $params);

        return collect($params)
            ->only(self::PRESERVABLE_QUERY)
            ->filter(fn ($value) => is_string($value) && preg_match('/^[a-z0-9_-]{1,40}$/i', $value) === 1)
            ->all();
    }
}
