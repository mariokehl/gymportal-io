<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaddleIpMiddleware
{
    // Sandbox
    public $allowSandboxIps = [
        '34.194.127.46',
        '54.234.237.108',
        '3.208.120.145',
        '44.226.236.210',
        '44.241.183.62',
        '100.20.172.113'
    ];

    // Production
    public $allowLiveIps = [
        '34.232.58.13',
        '34.195.105.136',
        '34.237.3.244',
        '35.155.119.135',
        '52.11.166.252',
        '34.212.5.7'
    ];

    // DDEV, localhost, ...
    private $allowLocalIps = [
        '172.18.0.6'
    ];

    /**
     * Handle an incoming webhook request.
     *
     * See https://developer.paddle.com/webhooks/respond-to-webhooks#allow-paddle-ips
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->ip(), array_merge($this->allowSandboxIps, $this->allowLiveIps, $this->allowLocalIps))) {
            abort(403, "You are restricted to access the site.");
        }

        return $next($request);
    }
}
