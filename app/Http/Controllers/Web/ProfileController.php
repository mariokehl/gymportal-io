<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Gym;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Display the user's profile page.
     */
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('Profile/Index', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'current_password.required' => 'Das aktuelle Passwort ist erforderlich.',
            'current_password.current_password' => 'Das aktuelle Passwort ist nicht korrekt.',
            'password.required' => 'Das neue Passwort ist erforderlich.',
            'password.confirmed' => 'Die Passwörter stimmen nicht überein.',
            'password.min' => 'Das Passwort muss mindestens :min Zeichen lang sein.',
        ]);

        /** @var User $user */
        $user = Auth::user();

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Passwort erfolgreich geändert.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ], [
            'password.required' => 'Bitte geben Sie Ihr Passwort ein, um Ihr Konto zu löschen.',
            'password.current_password' => 'Das eingegebene Passwort ist nicht korrekt.',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Check if user owns any gyms with active subscriptions
        $gymsWithActiveSubscription = Gym::where('owner_id', $user->id)
            ->where(function ($query) {
                $query->where('subscription_status', 'active')
                    ->whereNotNull('subscription_ends_at')
                    ->where('subscription_ends_at', '>', now());
            })
            ->get();

        if ($gymsWithActiveSubscription->isNotEmpty()) {
            $gymNames = $gymsWithActiveSubscription->pluck('name')->join(', ');

            return back()->withErrors([
                'subscription' => "Die folgenden Organisationen haben noch ein aktives Abonnement: {$gymNames}. Bitte kündigen Sie zuerst alle Abonnements, bevor Sie Ihr Konto löschen.",
            ]);
        }

        DB::beginTransaction();

        try {
            // Get all gyms owned by this user
            $ownedGyms = Gym::where('owner_id', $user->id)->get();

            foreach ($ownedGyms as $gym) {
                // Clear subscription data for all owned gyms
                $gym->update([
                    'subscription_status' => null,
                    'subscription_ends_at' => null,
                    'paddle_subscription_id' => null,
                ]);

                // Soft delete the gym
                $gym->delete();
            }

            // Remove user from any gyms they're associated with (but don't own)
            DB::table('gym_users')->where('user_id', $user->id)->delete();

            // Delete the user (soft delete)
            $user->delete();

            DB::commit();

            // Logout and invalidate session after successful deletion
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('status', 'Ihr Konto wurde erfolgreich gelöscht.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Account deletion failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'deletion' => 'Es ist ein Fehler beim Löschen Ihres Kontos aufgetreten. Bitte versuchen Sie es später erneut.',
            ]);
        }
    }
}
