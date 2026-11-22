<?php

namespace App\Http\Middleware;

use App\Models\AgentApiLog;
use App\Models\GymApiClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates an Agent API client by Bearer token and checks the ability the
 * route asks for: `agent.auth:members.read`.
 *
 * The gym is taken from the token and attached to the request. Nothing in the
 * request can select another gym.
 */
class AuthenticateAgent
{
    private const MAX_FAILURES_PER_IP = 10;

    private const DECAY_SECONDS = 900;

    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $throttleKey = 'agent-auth:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILURES_PER_IP)) {
            return $this->reject($request, $throttleKey, 'IP blocked after repeated failures', count: false);
        }

        $token = $request->bearerToken();

        if (! $token) {
            return $this->reject($request, $throttleKey, 'No token provided');
        }

        $client = GymApiClient::findByToken($token);

        if (! $client || ! $client->isUsable() || ! $client->gym) {
            return $this->reject($request, $throttleKey, match (true) {
                ! $client => 'Unknown token',
                ! $client->gym => 'Gym removed',
                default => 'Token revoked or expired',
            });
        }

        RateLimiter::clear($throttleKey);

        // Same rule as the portal and the scheduled jobs: no trial and no
        // subscription, no access.
        if (! $client->gym->canAccessPremiumFeatures()) {
            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'gym_without_access',
                    'message' => 'The gym has no active trial or subscription.',
                ],
            ], 403);
        }

        $request->attributes->set('agent_client', $client);
        $request->attributes->set('agent_gym', $client->gym);
        $request->attributes->set('agent_ability', $ability);

        if ($ability !== null && ! $client->can($ability)) {
            $response = new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'missing_ability',
                    'message' => 'This token is not allowed to do this.',
                    'required_ability' => $ability,
                ],
            ], 403);

            $this->log($request, $client, $response);

            return $response;
        }

        $client->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->saveQuietly();

        $response = $next($request);

        // Nothing here is meant for a search index.
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        $this->log($request, $client, $response);

        return $response;
    }

    private function log(Request $request, GymApiClient $client, Response $response): void
    {
        try {
            AgentApiLog::create([
                'gym_id' => $client->gym_id,
                'gym_api_client_id' => $client->id,
                'method' => $request->method(),
                'path' => substr($request->path(), 0, 255),
                'ability' => $request->attributes->get('agent_ability'),
                'member_id' => $request->attributes->get('agent_member_id'),
                'status' => $response->getStatusCode(),
                'changed_data' => (bool) $request->attributes->get('agent_changed_data', false),
                'summary' => $request->attributes->get('agent_summary'),
                'changes' => $request->attributes->get('agent_changes'),
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // The audit trail must never take a request down with it.
            Log::error('Agent API audit log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Uniform rejection: a caller cannot tell an unknown token from a revoked
     * one. The real reason only goes to the log.
     */
    private function reject(Request $request, string $throttleKey, string $reason, bool $count = true): JsonResponse
    {
        if ($count) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
        }

        Log::warning('Agent API authentication failed', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'path' => $request->path(),
        ]);

        return new JsonResponse([
            'success' => false,
            'error' => [
                'code' => 'unauthenticated',
                'message' => 'Authentication failed',
            ],
        ], 401);
    }
}
