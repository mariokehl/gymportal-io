<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Gym;
use App\Models\LoginCode;
use App\Mail\LoginCodeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function sendLoginCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'gym_slug' => 'required|string'
        ]);

        // Rate Limiting
        $key = 'login-code:' . $request->ip() . ':' . $request->email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Zu viele Versuche. Bitte warten Sie vor dem nächsten Versuch.',
                'error_code' => 'RATE_LIMITED'
            ], 429);
        }

        $gym = Gym::where('slug', $request->gym_slug)
                  ->where('pwa_enabled', true)
                  ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Gym nicht gefunden oder PWA nicht aktiviert',
                'error_code' => 'GYM_NOT_FOUND'
            ], 404);
        }

        $member = Member::where('email', $request->email)
                       ->where('gym_id', $gym->id)
                       ->first();

        if (!$member) {
            // Rate limiting auch für ungültige E-Mails
            RateLimiter::hit($key, 300); // 5 Minuten

            return response()->json([
                'success' => false,
                'message' => 'Mitglied nicht gefunden',
                'error_code' => 'MEMBER_NOT_FOUND'
            ], 404);
        }

        if ($member->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Mitgliedschaft ist nicht aktiv',
                'error_code' => 'MEMBER_INACTIVE'
            ], 403);
        }

        try {
            // LoginCode erstellen
            $loginCode = LoginCode::createForMember($member);

            // E-Mail senden
            Mail::to($member->email)->send(
                new LoginCodeMail($loginCode, $member, $gym)
            );

            // Rate limiting für erfolgreiche Anfragen
            RateLimiter::hit($key, 60); // 1 Minute

            Log::info('Login code sent', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Anmeldecode wurde versendet',
                'expires_in' => 600 // 10 Minuten in Sekunden
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send login code', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Versenden der E-Mail. Bitte versuchen Sie es später erneut.',
                'error_code' => 'EMAIL_SEND_FAILED'
            ], 500);
        }
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'gym_slug' => 'required|string'
        ]);

        // Rate Limiting für Code-Verifikation
        $key = 'verify-code:' . $request->ip() . ':' . $request->email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Zu viele fehlgeschlagene Versuche. Bitte warten Sie.',
                'error_code' => 'RATE_LIMITED'
            ], 429);
        }

        $gym = Gym::where('slug', $request->gym_slug)
                  ->where('pwa_enabled', true)
                  ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Gym nicht gefunden',
                'error_code' => 'GYM_NOT_FOUND'
            ], 404);
        }

        /** @var Member $member */
        $member = Member::where('email', $request->email)
                       ->where('gym_id', $gym->id)
                       ->first();

        if (!$member) {
            RateLimiter::hit($key, 300);

            return response()->json([
                'success' => false,
                'message' => 'Mitglied nicht gefunden',
                'error_code' => 'MEMBER_NOT_FOUND'
            ], 404);
        }

        $loginCode = LoginCode::findValidCode($request->code, $member->id);

        if (!$loginCode) {
            RateLimiter::hit($key, 60);

            return response()->json([
                'success' => false,
                'message' => 'Ungültiger oder abgelaufener Code',
                'error_code' => 'INVALID_CODE'
            ], 422);
        }

        // Code als verwendet markieren
        $loginCode->markAsUsed();

        // Token erstellen
        $token = $member->createToken('member-pwa', ['member-pwa'])->plainTextToken;

        // Rate limit zurücksetzen bei erfolgreichem Login
        RateLimiter::clear($key);

        Log::info('Member logged in successfully', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Anmeldung erfolgreich',
            'token' => $token,
            'member' => $member->only([
                'id', 'email', 'first_name', 'last_name',
                'member_number', 'phone', 'address',
                'city', 'postal_code', 'status'
            ]),
            'gym' => $gym->getMemberAppData()
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout erfolgreich'
        ]);
    }
}
