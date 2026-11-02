<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated Not used since v0.0.1; never referenced since it was introduced.
 */
class ForceJsonResponse
{
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
