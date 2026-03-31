<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgeVerified
{
    /**
     * Ensure the authenticated member has completed age verification.
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
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isAgeVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Altersverifizierung erforderlich. Bitte verifiziere dein Alter, bevor du fortfährst.',
                'error_code' => 'AGE_VERIFICATION_REQUIRED',
                'age_verified' => false,
            ], 403);
        }

        return $next($request);
    }
}
