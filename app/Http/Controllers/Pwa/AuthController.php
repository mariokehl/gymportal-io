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

        // Check if member has a static login code configured (for App Store review)
        if ($member->accessConfig && $member->accessConfig->hasStaticLoginCode()) {
            // Rate limiting für erfolgreiche Anfragen
            RateLimiter::hit($key, 60); // 1 Minute

            Log::info('Static login code requested (no email sent)', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Anmeldecode wurde versendet',
                'expires_in' => 600 // 10 Minuten in Sekunden (same as normal flow)
            ]);
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

        // Check if member has a static login code configured
        if ($member->accessConfig && $member->accessConfig->hasStaticLoginCode()) {
            // Verify against static code
            if ($request->code !== $member->accessConfig->static_login_code) {
                RateLimiter::hit($key, 60);

                return response()->json([
                    'success' => false,
                    'message' => 'Ungültiger oder abgelaufener Code',
                    'error_code' => 'INVALID_CODE'
                ], 422);
            }

            // Static code is valid - no need to mark as used
            // (it can be reused for App Store review)
        } else {
            // Normal flow: check database login code
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
        }

        // Token erstellen (full session - user verified via email code)
        $token = $member->createToken('member-pwa-full', ['member-pwa', 'full'])->plainTextToken;

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
            'token_type' => 'full',
            'member' => $this->getFullMemberData($member, $gym),
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

    /**
     * Link a contract anonymously using email and birth date.
     * Returns a limited anonymous token with masked member data.
     */
    public function linkContractAnonymous(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'birth_date' => 'required|date',
            'gym_slug' => 'required|string'
        ]);

        // Rate Limiting
        $key = 'link-contract:' . $request->ip() . ':' . $request->email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
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

        // Find member by email and birth_date
        $member = Member::where('email', $request->email)
                       ->where('gym_id', $gym->id)
                       ->whereDate('birth_date', $request->birth_date)
                       ->first();

        if (!$member) {
            RateLimiter::hit($key, 300);

            return response()->json([
                'message' => 'Kein Vertrag mit dieser E-Mail und Geburtsdatum gefunden.'
            ], 404);
        }

        // Create anonymous token with limited abilities
        $token = $member->createToken('member-pwa-anonymous', ['member-pwa', 'anonymous'])->plainTextToken;

        // Send verification code for potential upgrade
        //try {
        //    $loginCode = LoginCode::createForMember($member);
        //    Mail::to($member->email)->send(
        //        new LoginCodeMail($loginCode, $member, $gym)
        //    );
        //} catch (\Exception $e) {
        //    Log::warning('Failed to send verification code during anonymous link', [
        //        'member_id' => $member->id,
        //        'error' => $e->getMessage()
        //    ]);
        //}

        RateLimiter::clear($key);

        Log::info('Member linked contract anonymously', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'token' => $token,
            'token_type' => 'anonymous',
            'member' => $this->getMaskedMemberData($member, $gym)
        ]);
    }

    /**
     * Upgrade an anonymous session to a full session using verification code.
     */
    public function upgradeSession(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'gym_slug' => 'required|string'
        ]);

        /** @var Member $member */
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Nicht authentifiziert',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        // Rate Limiting
        $key = 'upgrade-session:' . $request->ip() . ':' . $member->id;
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

        // Verify member belongs to this gym
        if ($member->gym_id !== $gym->id) {
            return response()->json([
                'success' => false,
                'message' => 'Ungültige Gym-Zuordnung',
                'error_code' => 'INVALID_GYM'
            ], 403);
        }

        // Check if member has a static login code configured
        if ($member->accessConfig && $member->accessConfig->hasStaticLoginCode()) {
            // Verify against static code
            if ($request->code !== $member->accessConfig->static_login_code) {
                RateLimiter::hit($key, 60);

                return response()->json([
                    'success' => false,
                    'message' => 'Ungültiger oder abgelaufener Code',
                    'error_code' => 'INVALID_CODE'
                ], 422);
            }

            // Static code is valid - no need to mark as used
            // (it can be reused for App Store review)
        } else {
            // Normal flow: check database login code
            $loginCode = LoginCode::findValidCode($request->code, $member->id);

            if (!$loginCode) {
                RateLimiter::hit($key, 60);

                return response()->json([
                    'success' => false,
                    'message' => 'Ungültiger oder abgelaufener Code',
                    'error_code' => 'INVALID_CODE'
                ], 422);
            }

            // Mark code as used
            $loginCode->markAsUsed();
        }

        // Delete current anonymous token
        $member->currentAccessToken()->delete();

        // Create new full session token
        $token = $member->createToken('member-pwa-full', ['member-pwa', 'full'])->plainTextToken;

        RateLimiter::clear($key);

        Log::info('Member upgraded to full session', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'token' => $token,
            'token_type' => 'full',
            'member' => $this->getFullMemberData($member, $gym)
        ]);
    }

    /**
     * Get masked member data for anonymous sessions.
     */
    private function getMaskedMemberData(Member $member, Gym $gym): array
    {
        return [
            'id' => $member->id,
            'member_number' => $member->member_number,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'phone_masked' => $member->masked_phone,
            'address_masked' => $member->masked_address,
            'postal_code_masked' => $member->masked_postal_code,
            'city_masked' => $member->masked_city,
            'birth_date_masked' => $member->masked_birth_date,
            'status' => $member->status,
            'avatar_url' => null,
            'joined_date' => $member->joined_date?->format('Y-m-d'),
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'slug' => $gym->slug
            ],
            'is_verified' => false
        ];
    }

    /**
     * Get full member data for verified sessions.
     */
    private function getFullMemberData(Member $member, Gym $gym): array
    {
        return [
            'id' => $member->id,
            'member_number' => $member->member_number,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'phone' => $member->phone,
            'address' => $member->address,
            'postal_code' => $member->postal_code,
            'city' => $member->city,
            'status' => $member->status,
            'birth_date' => $member->birth_date?->format('Y-m-d'),
            'status' => $member->status,
            'avatar_url' => null,
            'joined_date' => $member->joined_date?->format('Y-m-d'),
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'slug' => $gym->slug
            ],
            'is_verified' => true
        ];
    }
}
