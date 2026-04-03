<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\LoginCode;
use App\Models\Member;
use App\Mail\LoginCodeMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

abstract class PwaAuthController extends Controller
{
    /**
     * Token scope prefix, e.g. 'member-pwa' or 'guest-pwa'.
     */
    abstract protected function tokenScope(): string;

    /**
     * Token expiration for full sessions.
     */
    abstract protected function tokenExpiration(Request $request): \DateTimeInterface;

    /**
     * Gym filter scope, e.g. ['pwa_enabled' => true] or ['guest_enabled' => true].
     */
    abstract protected function gymFilterScope(): array;

    /**
     * Build the member data array returned after successful login.
     */
    abstract protected function memberResponseData(Member $member, Gym $gym): array;

    /**
     * Build the gym data array returned after successful login.
     */
    abstract protected function gymResponseData(Gym $gym): array;

    /**
     * Rate limit key prefix to avoid collisions between member and guest flows.
     */
    abstract protected function rateLimitPrefix(): string;

    public function sendLoginCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'gym_slug' => 'required|string',
        ]);

        $email = strtolower($request->email);

        $key = $this->rateLimitPrefix() . '-login:' . $request->ip() . ':' . $email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return $this->rateLimitedResponse();
        }

        $gym = $this->resolveGymOrFail($request);
        if ($gym instanceof JsonResponse) {
            return $gym;
        }

        $member = $this->findMember($email, $gym);

        if (!$member) {
            RateLimiter::hit($key, 300);

            return response()->json([
                'success' => false,
                'message' => 'Mitglied nicht gefunden',
                'error_code' => 'MEMBER_NOT_FOUND',
            ], 404);
        }

        if (!$this->isMemberEligible($member)) {
            return response()->json([
                'success' => false,
                'message' => 'Mitgliedschaft ist nicht aktiv',
                'error_code' => 'MEMBER_INACTIVE',
            ], 403);
        }

        // Hook for subclass-specific pre-send checks (e.g. static codes, device limits)
        $preCheck = $this->beforeSendLoginCode($request, $member, $gym);
        if ($preCheck instanceof JsonResponse) {
            return $preCheck;
        }

        return $this->createAndSendCode($member, $gym, $key, $request);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'gym_slug' => 'required|string',
        ]);

        $email = strtolower($request->email);

        $key = $this->rateLimitPrefix() . '-verify:' . $request->ip() . ':' . $email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Zu viele fehlgeschlagene Versuche. Bitte warte.',
                'error_code' => 'RATE_LIMITED',
            ], 429);
        }

        $gym = $this->resolveGymOrFail($request);
        if ($gym instanceof JsonResponse) {
            return $gym;
        }

        $member = $this->findMember($email, $gym);

        if (!$member) {
            RateLimiter::hit($key, 300);

            return response()->json([
                'success' => false,
                'message' => 'Mitglied nicht gefunden',
                'error_code' => 'MEMBER_NOT_FOUND',
            ], 404);
        }

        // Verify the code (subclass can override for static codes etc.)
        $codeResult = $this->verifyLoginCode($request, $member);
        if ($codeResult instanceof JsonResponse) {
            RateLimiter::hit($key, 60);

            return $codeResult;
        }

        $tokenName = $this->tokenScope() . '-full';
        $token = $member->createToken(
            $tokenName,
            [$this->tokenScope(), 'full'],
            $this->tokenExpiration($request)
        )->plainTextToken;

        // Hook for subclass-specific post-verify actions (e.g. device registration)
        $this->afterVerifyCode($request, $member, $gym);

        RateLimiter::clear($key);

        Log::info('Member logged in successfully', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Anmeldung erfolgreich',
            'token' => $token,
            'token_type' => 'full',
            'member' => $this->memberResponseData($member, $gym),
            'gym' => $this->gymResponseData($gym),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout erfolgreich',
        ]);
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    protected function resolveGymOrFail(Request $request): Gym|JsonResponse
    {
        $gym = Gym::where('slug', $request->gym_slug)
            ->where($this->gymFilterScope())
            ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Studio nicht gefunden oder Zugang nicht aktiviert',
                'error_code' => 'GYM_NOT_FOUND',
            ], 404);
        }

        return $gym;
    }

    protected function findMember(string $email, Gym $gym): ?Member
    {
        return Member::whereRaw('LOWER(email) = ?', [$email])
            ->where('gym_id', $gym->id)
            ->first();
    }

    /**
     * Check if the member is eligible to authenticate.
     * Override in subclass if different status requirements apply.
     */
    protected function isMemberEligible(Member $member): bool
    {
        return in_array($member->status, ['active', 'extern']);
    }

    /**
     * Hook called before sending a login code.
     * Return a JsonResponse to short-circuit, or null to continue.
     */
    protected function beforeSendLoginCode(Request $request, Member $member, Gym $gym): ?JsonResponse
    {
        return null;
    }

    /**
     * Verify the login code. Return null on success, or a JsonResponse on failure.
     * Override in subclass for static login code support.
     */
    protected function verifyLoginCode(Request $request, Member $member): ?JsonResponse
    {
        $loginCode = LoginCode::findValidCode($request->code, $member->id);

        if (!$loginCode) {
            return response()->json([
                'success' => false,
                'message' => 'Ungültiger oder abgelaufener Code',
                'error_code' => 'INVALID_CODE',
            ], 422);
        }

        $loginCode->markAsUsed();

        return null;
    }

    /**
     * Hook called after successful code verification (before response).
     */
    protected function afterVerifyCode(Request $request, Member $member, Gym $gym): void
    {
        // no-op by default
    }

    protected function createAndSendCode(Member $member, Gym $gym, string $rateLimitKey, Request $request): JsonResponse
    {
        try {
            $loginCode = LoginCode::createForMember($member);

            Mail::to($member->email)->send(
                new LoginCodeMail($loginCode, $member, $gym)
            );

            RateLimiter::hit($rateLimitKey, 60);

            Log::info('Login code sent', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Anmeldecode wurde versendet',
                'expires_in' => 600,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send login code', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Versenden der E-Mail. Bitte versuche es später erneut.',
                'error_code' => 'EMAIL_SEND_FAILED',
            ], 500);
        }
    }

    protected function rateLimitedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Zu viele Versuche. Bitte warte vor dem nächsten Versuch.',
            'error_code' => 'RATE_LIMITED',
        ], 429);
    }
}
