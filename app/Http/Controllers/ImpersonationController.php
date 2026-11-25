<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class ImpersonationController extends Controller
{
    private const SESSION_KEY = 'impersonator_id';

    private const ADMIN_ROLE_ID = 1;

    /**
     * Zeigt eine Liste aller Benutzer, die simuliert werden können
     */
    public function index(): Response
    {
        // Prüfe ob Benutzer eingeloggt und Administrator ist
        if (! Auth::check() || Auth::user()->role_id !== self::ADMIN_ROLE_ID) {
            abort(403, 'Sie haben keine Berechtigung für diese Aktion.');
        }

        $users = User::where('id', '!=', Auth::id())
            ->where('role_id', '!=', self::ADMIN_ROLE_ID)
            ->select('id', 'first_name', 'last_name', 'email', 'email_verified_at', 'created_at', 'is_blocked', 'blocked_reason')
            ->withCount([
                'ownedGyms',
                'ownedGyms as paid_gyms_count' => fn ($query) => $query->withActiveSubscription(),
                'ownedGyms as trial_gyms_count' => fn ($query) => $query->inTrialWithoutSubscription(),
                'ownedGyms as expired_gyms_count' => fn ($query) => $query->withoutActiveAccess(),
            ])
            ->orderBy('id', 'desc')
            ->get()
            // Paying customers first (most paid subscriptions on top), then running
            // trials, then everyone else; the newest user first within a group.
            // Sorted in PHP: Postgres cannot use count aliases in ORDER BY expressions.
            ->sortBy([
                fn (User $a, User $b) => $this->listingGroup($a) <=> $this->listingGroup($b),
                fn (User $a, User $b) => $b->paid_gyms_count <=> $a->paid_gyms_count,
            ])
            ->values();

        return Inertia::render('Admin/Impersonation/Index', [
            'users' => $users,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'warning' => session('warning'),
            ],
        ]);
    }

    /**
     * Listing group of a user: 0 = paid subscription, 1 = running trial, 2 = rest.
     */
    private function listingGroup(User $user): int
    {
        return match (true) {
            $user->paid_gyms_count > 0 => 0,
            $user->trial_gyms_count > 0 => 1,
            default => 2,
        };
    }

    /**
     * Startet die Impersonation eines anderen Benutzers
     *
     * @return RedirectResponse
     */
    public function impersonate(Request $request, User $user)
    {
        // Prüfe ob Benutzer eingeloggt und Administrator ist
        if (! Auth::check() || Auth::user()->role_id !== self::ADMIN_ROLE_ID) {
            return redirect()->back()->with('error', 'Keine Berechtigung für diese Aktion.');
        }

        // Verhindere Selbst-Impersonation
        if (Auth::id() === $user->id) {
            return redirect()->back()->with('error', 'Sie können sich nicht selbst simulieren.');
        }

        // Prüfe ob bereits eine Impersonation aktiv ist
        if ($this->isImpersonating()) {
            return redirect()->back()->with('error', 'Es ist bereits eine Simulation aktiv. Bitte beenden Sie diese zuerst.');
        }

        // Verhindere Impersonation von anderen Administratoren
        if ($user->role_id === self::ADMIN_ROLE_ID) {
            return redirect()->back()->with('error', 'Administratoren können nicht simuliert werden.');
        }

        // Blocked users would be logged out right away by the blocked.check middleware
        if ($user->isBlocked()) {
            return redirect()->back()->with('error', 'Blockierte Benutzer können nicht simuliert werden.');
        }

        // Speichere die ID des ursprünglichen Benutzers
        $originalUserId = Auth::id();
        $originalUser = Auth::user();
        Session::put(self::SESSION_KEY, $originalUserId);

        // Speichere zusätzliche Infos für die UI
        Session::put('impersonator_name', $originalUser->full_name);
        Session::put('impersonated_user_name', $user->full_name);
        Session::put('impersonated_user_email', $user->email);

        // Logge die Impersonation
        Log::info('Benutzer-Impersonation gestartet', [
            'impersonator_id' => $originalUserId,
            'impersonator_email' => $originalUser->email,
            'impersonator_name' => $originalUser->first_name.' '.$originalUser->last_name,
            'target_user_id' => $user->id,
            'target_user_email' => $user->email,
            'target_user_name' => $user->first_name.' '.$user->last_name,
            'ip_address' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Führe die Impersonation durch
        Auth::logout();
        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('impersonation_started', true)
            ->with('success', 'Simulation von '.$user->full_name.' erfolgreich gestartet.');
    }

    /**
     * Beendet die aktuelle Impersonation und kehrt zum ursprünglichen Benutzer zurück
     *
     * @return RedirectResponse
     */
    public function stopImpersonating(Request $request)
    {
        if (! $this->isImpersonating()) {
            return redirect()->route('dashboard')
                ->with('info', 'Es ist keine Simulation aktiv.');
        }

        $originalUserId = Session::get(self::SESSION_KEY);
        $currentUser = Auth::user();

        // Finde den ursprünglichen Benutzer
        $originalUser = User::findOrFail($originalUserId);

        // Logge das Ende der Impersonation
        if ($currentUser) {
            Log::info('Benutzer-Impersonation beendet', [
                'impersonator_id' => $originalUserId,
                'impersonator_email' => $originalUser->email,
                'impersonator_name' => $originalUser->first_name.' '.$originalUser->last_name,
                'impersonated_user_id' => $currentUser->id,
                'impersonated_user_email' => $currentUser->email,
                'impersonated_user_name' => $currentUser->first_name.' '.$currentUser->last_name,
                'ip_address' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        // Beende die Impersonation
        Auth::logout();
        Auth::login($originalUser);

        // Entferne alle Session-Variablen
        Session::forget([
            self::SESSION_KEY,
            'impersonator_name',
            'impersonated_user_name',
            'impersonated_user_email',
        ]);

        return redirect()->route('dashboard')
            ->with('impersonation_stopped', true)
            ->with('success', 'Simulation erfolgreich beendet. Willkommen zurück!');
    }

    /**
     * API Endpoint: Gibt den aktuellen Impersonation-Status zurück
     *
     * @return JsonResponse
     */
    public function status()
    {
        return response()->json([
            'isImpersonating' => $this->isImpersonating(),
            'impersonatorId' => $this->getImpersonatorId(),
            'impersonatorName' => Session::get('impersonator_name'),
            'impersonatedUserName' => Session::get('impersonated_user_name'),
            'impersonatedUserEmail' => Session::get('impersonated_user_email'),
        ]);
    }

    /**
     * Prüft, ob aktuell eine Impersonation aktiv ist
     */
    private function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /**
     * Gibt die ID des ursprünglichen Benutzers zurück (falls Impersonation aktiv)
     */
    private function getImpersonatorId(): ?int
    {
        return Session::get(self::SESSION_KEY);
    }
}
