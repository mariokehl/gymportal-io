<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFullSession
{
    /**
     * Handle an incoming request.
     *
     * Ensures that the request has a full session token (not anonymous).
     * Anonymous tokens have 'anonymous' in their abilities, full tokens have 'full'.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Nicht authentifiziert',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        $token = $user->currentAccessToken();

        // Check if token has 'full' ability (upgraded session)
        if (!$token || !$token->can('full')) {
            return response()->json([
                'success' => false,
                'message' => 'Vollständige Verifizierung erforderlich. Bitte verifiziere deine Identität.',
                'error_code' => 'VERIFICATION_REQUIRED',
                'is_verified' => false
            ], 403);
        }

        return $next($request);
    }
}
