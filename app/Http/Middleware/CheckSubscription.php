<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return $next($request);
        }

        /** @var User $user */
        $user = Auth::user();
        /** @var Gym $gym */
        $gym = $user->currentGym;

        if (! $gym) {
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
            'profile.appearance.update',
            // Leaving an expired organization for another one must stay
            // possible; the target organization is checked on the next request.
            'user.switch-organization',
            // A new organization starts with its own trial and becomes the
            // current one, so it is not bound by this organization's status.
            'gyms.create',
            'gyms.store',
            // Import/Export (Datenexport soll immer möglich sein)
            'data-transfer.index',
            'data-transfer.export',
            'data-transfer.validate',
            'data-transfer.import',
        ];

        // Wenn weder Testphase noch aktive Subscription
        if (! $isInTrial && ! $gym->hasActiveSubscription()) {
            $routeName = $request->route()->getName();

            // Redirect zu Billing-Seite wenn Route nicht erlaubt
            if (! in_array($routeName, $allowedRoutes)) {
                return redirect()->route('billing.index')
                    ->with('error', 'Ihre Testphase ist abgelaufen. Bitte schließen Sie ein Abonnement ab, um alle Funktionen zu nutzen.');
            }
        }

        return $next($request);
    }
}
