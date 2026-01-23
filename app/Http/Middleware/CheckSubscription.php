<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        /** @var User $user */
        $user = Auth::user();
        /** @var Gym $gym */
        $gym = $user->currentGym;

        if (!$gym) {
            return $next($request);
        }

        // Prüfe Testphase
        $trialEndsAt = $gym->trial_ends_at;
        $isInTrial = now()->lt($trialEndsAt);

        // Erlaubte Routen während abgelaufener Testphase/Subscription
        $allowedRoutes = [
            'billing.index',
            'billing.subscribe',
            'billing.cancel',
            'billing.webhook',
            'dashboard', // Erlaubt, aber mit Einschränkungen
            'settings.index',
            'logout',
            // Profil (Benutzer sollen ihre Daten verwalten können)
            'profile.index',
            'profile.password.update',
            'profile.destroy',
            // Import/Export (Datenexport soll immer möglich sein)
            'data-transfer.index',
            'data-transfer.export',
            'data-transfer.validate',
            'data-transfer.import',
        ];

        // Wenn weder Testphase noch aktive Subscription
        if (!$isInTrial && !$gym->hasActiveSubscription()) {
            $routeName = $request->route()->getName();

            // Redirect zu Billing-Seite wenn Route nicht erlaubt
            if (!in_array($routeName, $allowedRoutes)) {
                return redirect()->route('billing.index')
                    ->with('error', 'Ihre Testphase ist abgelaufen. Bitte schließen Sie ein Abonnement ab, um alle Funktionen zu nutzen.');
            }
        }

        return $next($request);
    }
}
