<?php

namespace App\Http\Middleware;

use App\Models\GymScanner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthenticateScanner
{
    /**
     * Failed attempts allowed per IP before it is blocked entirely.
     */
    private const MAX_ATTEMPTS_PER_IP = 10;

    /**
     * How long a blocked IP stays out, in seconds.
     */
    private const DECAY_SECONDS = 900;

    /**
     * Message returned for every rejection.
     *
     * Deliberately uniform: an attacker must not be able to tell an unknown
     * token from a known one belonging to a locked or inactive device. The
     * actual reason goes to the log, not over the wire.
     */
    private const REJECTION_MESSAGE = 'Authentication failed';

    /**
     * Handle Scanner authentication via Bearer Token
     */
    public function handle(Request $request, Closure $next)
    {
        $throttleKey = 'scanner-auth:'.$request->ip();

        // Guessing is stopped before the token lookup — an attacker must not be
        // able to probe the database once the IP has burnt its attempts.
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_IP)) {
            return $this->reject($request, 'IP blocked after repeated failures', [
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ]);
        }

        // Extract the token from the various supported sources
        $token = $this->extractToken($request);

        if (! $token) {
            return $this->reject($request, 'No API token provided');
        }

        // Find the scanner by the hash of its token, so the plaintext never
        // reaches the query and a stolen database dump yields no usable keys.
        $scanner = GymScanner::findByToken($token);

        // Every rejection below counts against the IP, whether or not the token
        // resolved to a device. Only counting known scanners would leave the
        // actual attack — guessing unknown tokens — completely unmetered.
        if (! $scanner) {
            return $this->reject($request, 'Invalid API token');
        }

        // Check the scanner status
        if (! $scanner->isAccessible()) {
            $scanner->registerFailedAttempt();

            return $this->reject($request, $this->inaccessibleReason($scanner), [
                'scanner_id' => $scanner->id,
            ]);
        }

        // Check the IP whitelist (optional)
        if (! $scanner->isIpAllowed($request->ip())) {
            $scanner->registerFailedAttempt();

            return $this->reject($request, 'IP address not allowed', [
                'scanner_id' => $scanner->id,
            ]);
        }

        // Scanner authenticated successfully
        RateLimiter::clear($throttleKey);
        $scanner->resetFailedAttempts();
        $scanner->touch(); // Refresh last seen

        // Attach the scanner to the request for later access
        $request->merge(['scanner' => $scanner]);
        $request->setUserResolver(function () use ($scanner) {
            return $scanner;
        });

        return $next($request);
    }

    /**
     * Extract the token from the request
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

        // 3. Query parameter (legacy support)
        if ($token = $request->query('api_token')) {
            return $token;
        }

        // 4. Form data (for POST requests from the scanner)
        if ($token = $request->input('api_token')) {
            return $token;
        }

        return null;
    }

    /**
     * Why a known scanner was refused. For the log only.
     */
    private function inaccessibleReason(GymScanner $scanner): string
    {
        if ($scanner->locked_until && $scanner->locked_until->isFuture()) {
            return 'Scanner temporarily locked due to failed attempts';
        }

        if ($scanner->token_expires_at && $scanner->token_expires_at->isPast()) {
            return 'API token expired';
        }

        return 'Scanner is inactive';
    }

    /**
     * Count the attempt against the IP and answer with the uniform rejection.
     *
     * @param  array<string, mixed>  $context  Extra detail for the log entry.
     */
    private function reject(Request $request, string $reason, array $context = []): Response
    {
        RateLimiter::hit('scanner-auth:'.$request->ip(), self::DECAY_SECONDS);

        // No token material in the log — the scanner ID identifies the device
        // once it is known, and a prefix of a guessed token is of no use.
        Log::warning('Scanner authentication failed', array_merge([
            'reason' => $reason,
            'ip' => $request->ip(),
            'path' => $request->path(),
        ], $context));

        // The scanner expects a plain-text response
        return response("code=9999\nerror=".self::REJECTION_MESSAGE, 401)
            ->header('Content-Type', 'text/plain');
    }
}
