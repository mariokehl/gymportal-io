<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\LoginCode;
use App\Models\Member;
use App\Mail\LoginCodeMail;
use App\Services\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class GuestAuthController extends Controller
{
    public function __construct(
        private GuestService $guestService
    ) {}

    /**
     * Register a new guest member and send OTP.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|min:2|max:255',
            'last_name' => 'required|string|min:2|max:255',
            'birth_date' => 'required|date|before:today',
            'email' => 'required|email|max:255',
            'gym_slug' => 'required|string',
        ]);

        $email = strtolower($request->email);

        // Rate limiting
        $key = 'guest-register:' . $request->ip() . ':' . $email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Zu viele Versuche. Bitte warte vor dem nächsten Versuch.',
                'error_code' => 'RATE_LIMITED',
            ], 429);
        }

        $gym = Gym::where('slug', $request->gym_slug)
            ->where('guest_enabled', true)
            ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Studio nicht gefunden oder Gäste-Zugang nicht aktiviert.',
            ], 404);
        }

        // Check if member already exists
        $existingMember = Member::whereRaw('LOWER(email) = ?', [$email])
            ->where('gym_id', $gym->id)
            ->first();

        if ($existingMember) {
            if ($existingMember->guest_access) {
                return response()->json([
                    'success' => false,
                    'message' => 'Du hast bereits ein Gäste-Konto. Bitte melde dich an.',
                    'error_code' => 'GUEST_EXISTS',
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Du bist bereits Mitglied. Bitte nutze die Mitglieder-App.',
                'error_code' => 'MEMBER_EXISTS',
            ], 409);
        }

        // Create guest member
        $member = $this->guestService->createGuestMember($gym, [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $email,
            'birth_date' => $request->birth_date,
        ]);

        // Send OTP
        try {
            $loginCode = LoginCode::createForMember($member);
            Mail::to($member->email)->send(
                new LoginCodeMail($loginCode, $member, $gym)
            );

            RateLimiter::hit($key, 60);

            Log::info('Guest registered and code sent', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registrierung erfolgreich. Anmeldecode wurde versendet.',
                'expires_in' => 600,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send guest registration code', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Versenden der E-Mail. Bitte versuche es später erneut.',
                'error_code' => 'EMAIL_SEND_FAILED',
            ], 500);
        }
    }

    /**
     * Send login code to existing guest.
     */
    public function sendLoginCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'gym_slug' => 'required|string',
        ]);

        $email = strtolower($request->email);

        $key = 'guest-login:' . $request->ip() . ':' . $email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'Zu viele Versuche. Bitte warte vor dem nächsten Versuch.',
                'error_code' => 'RATE_LIMITED',
            ], 429);
        }

        $gym = Gym::where('slug', $request->gym_slug)
            ->where('guest_enabled', true)
            ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Studio nicht gefunden.',
            ], 404);
        }

        $member = Member::whereRaw('LOWER(email) = ?', [$email])
            ->where('gym_id', $gym->id)
            ->where('guest_access', true)
            ->first();

        if (!$member) {
            RateLimiter::hit($key, 300);
            return response()->json([
                'success' => false,
                'message' => 'Kein Gäste-Konto mit dieser E-Mail gefunden.',
                'error_code' => 'GUEST_NOT_FOUND',
            ], 404);
        }

        try {
            $loginCode = LoginCode::createForMember($member);
            Mail::to($member->email)->send(
                new LoginCodeMail($loginCode, $member, $gym)
            );

            RateLimiter::hit($key, 60);

            return response()->json([
                'success' => true,
                'message' => 'Anmeldecode wurde versendet.',
                'expires_in' => 600,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send guest login code', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Versenden der E-Mail.',
                'error_code' => 'EMAIL_SEND_FAILED',
            ], 500);
        }
    }

    /**
     * Verify OTP code and create auth token.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'gym_slug' => 'required|string',
        ]);

        $email = strtolower($request->email);

        $key = 'guest-verify:' . $request->ip() . ':' . $email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Zu viele fehlgeschlagene Versuche. Bitte warte.',
                'error_code' => 'RATE_LIMITED',
            ], 429);
        }

        $gym = Gym::where('slug', $request->gym_slug)
            ->where('guest_enabled', true)
            ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Studio nicht gefunden.',
            ], 404);
        }

        $member = Member::whereRaw('LOWER(email) = ?', [$email])
            ->where('gym_id', $gym->id)
            ->where('guest_access', true)
            ->first();

        if (!$member) {
            RateLimiter::hit($key, 300);
            return response()->json([
                'success' => false,
                'message' => 'Gast nicht gefunden.',
                'error_code' => 'GUEST_NOT_FOUND',
            ], 404);
        }

        $loginCode = LoginCode::findValidCode($request->code, $member->id);

        if (!$loginCode) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'success' => false,
                'message' => 'Ungültiger oder abgelaufener Code.',
                'error_code' => 'INVALID_CODE',
            ], 422);
        }

        $loginCode->markAsUsed();

        // Create token with 30-day expiration for guests
        $token = $member->createToken(
            'guest-pwa-full',
            ['guest-pwa', 'full'],
            now()->addDays(30)
        )->plainTextToken;

        RateLimiter::clear($key);

        Log::info('Guest logged in', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Anmeldung erfolgreich.',
            'token' => $token,
            'member' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'birth_date' => $member->birth_date?->format('Y-m-d'),
                'age_verified' => $member->isAgeVerified(),
            ],
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->getDisplayName(),
                'slug' => $gym->slug,
                'logo_url' => $gym->logo_url,
                'theme' => $gym->theme,
            ],
        ]);
    }

    /**
     * Logout guest (delete current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Abmeldung erfolgreich.',
        ]);
    }
}
