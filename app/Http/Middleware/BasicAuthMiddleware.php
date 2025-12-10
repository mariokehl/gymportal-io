<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('auth.basic.username');
        $password = config('auth.basic.password');

        // Deny if Basic Auth is not configured
        if (empty($username) || empty($password)) {
            return response('Forbidden', 403);
        }

        // Check if Basic Auth credentials are provided
        if ($request->getUser() !== $username || $request->getPassword() !== $password) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Admin Area"'
            ]);
        }

        return $next($request);
    }
}
