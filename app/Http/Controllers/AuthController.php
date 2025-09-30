<?php
// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\Gym;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            /** @var User $user */
            $user = Auth::user();

            if ($user->isBlocked()) {
                $request->session()->put('blocked_user_id', $user->id);
                Auth::logout();
                return redirect()->route('blocked');
            }

            if (!$user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'Die angegebenen Anmeldedaten sind ungültig.',
        ]);
    }

    public function showRegister()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        // E-Mail zu Kleinbuchstaben konvertieren
        $request->merge([
            'email' => strtolower($request->email)
        ]);

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|indisposable|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms_accepted' => ['required', 'accepted'],
        ], [
            'terms_accepted.required' => 'Sie müssen den Allgemeinen Geschäftsbedingungen und der Datenschutzerklärung zustimmen.',
            'terms_accepted.accepted' => 'Sie müssen den Allgemeinen Geschäftsbedingungen und der Datenschutzerklärung zustimmen.',
        ]);

        DB::beginTransaction();

        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $gym = Gym::create([
                'name' => Gym::DEFAULT_ORGANIZATION_NAME,
                'owner_id' => $user->id,
                'address' => 'Mustergasse 1a',
                'city' => 'Hamburg',
                'postal_code' => '22761',
                'country' => 'DE',
                'phone' => '+4930123456789',
                'email' => $request->email,
            ]);

            // Set current_gym_id in users table
            $user->update(['current_gym_id' => $gym->id]);

            DB::commit();

            // Löse das Registered Event aus - das verschickt die Verifizierungs-E-Mail!
            event(new Registered($user));

            Auth::login($user);

            // Zur Verifizierungsseite weiterleiten statt direkt zum Dashboard
            return redirect()->route('verification.notice');

        } catch (Exception $e) {
            DB::rollback();

            // Log the error for debugging
            Log::error('Registration failed: ' . $e->getMessage());

            // Return back with error message
            return back()->withInput()->withErrors([
                'registration' => 'Registrierung fehlgeschlagen. Bitte versuchen Sie es erneut.'
            ]);
        }
    }

    /**
     * Zeige die E-Mail-Verifizierungsseite
     */
    public function showVerifyEmail()
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return redirect('/dashboard');
        }

        return Inertia::render('Auth/VerifyEmail');
    }

    /**
     * Verifiziere die E-Mail-Adresse
     */
    public function verifyEmail(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect('/dashboard')->with('message', 'E-Mail-Adresse erfolgreich bestätigt!');
    }

    /**
     * Sende die Verifizierungs-E-Mail erneut
     */
    public function resendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', 'Ein neuer Bestätigungslink wurde an Ihre E-Mail-Adresse gesendet.');
    }

    public function showForgotPassword()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
                    ? back()->with(['status' => 'Wir haben Ihnen einen Link zum Zurücksetzen des Passworts per E-Mail gesendet!'])
                    : back()->withErrors(['email' => 'Wir können keinen Benutzer mit dieser E-Mail-Adresse finden.']);
    }

    public function showResetPassword(Request $request, $token)
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $token,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', 'Ihr Passwort wurde erfolgreich zurückgesetzt!')
                    : back()->withErrors(['email' => [__($status)]]);
    }

    public function blocked(Request $request)
    {
        // Check if we have a blocked user in session or get the last blocked user info
        $user = null;
        $blockedReason = 'Ihr Account wurde gesperrt.';

        // Try to get the user from session if they were just logged out
        if ($request->session()->has('blocked_user_id')) {
            $user = User::find($request->session()->get('blocked_user_id'));
            if ($user && $user->isBlocked() && $user->blocked_reason) {
                $blockedReason = $user->blocked_reason;
            }
            $request->session()->forget('blocked_user_id');
        }

        return Inertia::render('Auth/Blocked', [
            'reason' => $blockedReason,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
