<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\GymScanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthenticateScanner
{
    /**
     * Handle Scanner authentication via Bearer Token
     */
    public function handle(Request $request, Closure $next)
    {
        // Token aus verschiedenen Quellen extrahieren
        $token = $this->extractToken($request);

        if (!$token) {
            return $this->unauthorizedResponse('No API token provided');
        }

        // Scanner anhand des Tokens finden
        $scanner = GymScanner::where('api_token', $token)->first();

        if (!$scanner) {
            return $this->unauthorizedResponse('Invalid API token');
        }

        // Scanner-Status prüfen
        if (!$scanner->isAccessible()) {
            $scanner->registerFailedAttempt();

            if ($scanner->locked_until && $scanner->locked_until->isFuture()) {
                return $this->unauthorizedResponse(
                    'Scanner temporarily locked due to failed attempts'
                );
            }

            if ($scanner->token_expires_at && $scanner->token_expires_at->isPast()) {
                return $this->unauthorizedResponse('API token expired');
            }

            return $this->unauthorizedResponse('Scanner is inactive');
        }

        // IP-Whitelist prüfen (optional)
        if (!$scanner->isIpAllowed($request->ip())) {
            $scanner->registerFailedAttempt();
            return $this->unauthorizedResponse(
                'IP address not allowed: ' . $request->ip()
            );
        }

        // Scanner erfolgreich authentifiziert
        $scanner->resetFailedAttempts();
        $scanner->touch(); // Last seen aktualisieren

        // Scanner an Request anhängen für späteren Zugriff
        $request->merge(['scanner' => $scanner]);
        $request->setUserResolver(function () use ($scanner) {
            return $scanner;
        });

        return $next($request);
    }

    /**
     * Token aus Request extrahieren
     */
    private function extractToken(Request $request): ?string
    {
        // 1. Authorization Header (Bearer Token)
        if ($request->bearerToken()) {
            return $request->bearerToken();
        }

        // 2. Custom Header
        if ($token = $request->header('X-Scanner-Token')) {
            return $token;
        }

        // 3. Query Parameter (für Legacy-Support)
        if ($token = $request->query('api_token')) {
            return $token;
        }

        // 4. Form Data (für POST requests vom Scanner)
        if ($token = $request->input('api_token')) {
            return $token;
        }

        return null;
    }

    /**
     * Unauthorized Response für Scanner (Plain Text)
     */
    private function unauthorizedResponse(string $message)
    {
        Log::warning('Scanner authentication failed', [
            'message' => $message,
            'ip' => request()->ip(),
            'token' => substr(request()->bearerToken() ?? '', 0, 10) . '***'
        ]);

        // Scanner erwartet Plain-Text Response
        return response("code=9999\nerror={$message}", 401)
            ->header('Content-Type', 'text/plain');
    }
}
