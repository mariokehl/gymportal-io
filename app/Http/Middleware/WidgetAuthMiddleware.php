<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Gym;

class WidgetAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');
        $studioId = $request->header('X-Studio-ID');

        // Prüfe API Key
        if (!$apiKey) {
            return response()->json([
                'error' => 'API Key fehlt',
                'message' => 'X-API-Key Header ist erforderlich'
            ], 401);
        }

        // Validiere API Key gegen Gym
        $gym = Gym::where('api_key', $apiKey)->first();

        if (!$gym) {
            return response()->json([
                'error' => 'Ungültiger API Key',
                'message' => 'Der angegebene API Key ist nicht gültig'
            ], 401);
        }

        // Prüfe Studio ID Übereinstimmung
        if ($studioId && $gym->id != $studioId) {
            return response()->json([
                'error' => 'Studio ID stimmt nicht überein',
                'message' => 'X-Studio-ID stimmt nicht mit API Key überein'
            ], 401);
        }

        //  Widget in den Einstellungen aktiv
        if (!$gym->widget_enabled) {
            return response()->json([
                'error' => 'Widget deaktiviert',
                'message' => 'Das Widget ist auf deiner Website nicht verfügbar'
            ], 403);
        }

        // Gym-Instanz in Request verfügbar machen
        $request->attributes->add(['gym' => $gym]);

        // Rate Limiting (optional)
        $maxRequests = 100; // pro Minute
        $cacheKey = 'widget_requests:' . $gym->id;

        if (cache()->has($cacheKey)) {
            $requests = cache()->get($cacheKey);
            if ($requests >= $maxRequests) {
                return response()->json([
                    'error' => 'Rate limit exceeded',
                    'message' => 'Zu viele Anfragen. Bitte versuche es später erneut.'
                ], 429);
            }
            cache()->put($cacheKey, $requests + 1, 60);
        } else {
            cache()->put($cacheKey, 1, 60);
        }

        return $next($request);
    }
}
