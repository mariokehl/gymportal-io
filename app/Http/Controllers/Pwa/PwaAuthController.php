<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Mail\Dispatching\MemberMailDispatcher;
use App\Mail\LoginCodeMail;
use App\Models\Gym;
use App\Models\LoginCode;
use App\Models\Member;
use App\Services\LoginCodeVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        $key = $this->rateLimitPrefix().'-login:'.$request->ip().':'.$email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return $this->rateLimitedResponse();
        }

        $gym = $this->resolveGymOrFail($request);
        if ($gym instanceof JsonResponse) {
            return $gym;
        }

        $member = $this->findMember($email, $gym);

        // Unknown and ineligible accounts get the same answer as a sent code,
        // so the endpoint cannot be used to find out who has an account.
        if (! $member || ! $this->isMemberEligible($member)) {
            RateLimiter::hit($key, 60);

            return $this->codeSentResponse();
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

        $key = $this->rateLimitPrefix().'-verify:'.$request->ip().':'.$email;
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

        // Same answer as a wrong code, so an unknown address is not revealed.
        if (! $member) {
            RateLimiter::hit($key, 60);

            return $this->invalidCodeResponse();
        }

        // Verify the code (subclass can override for static codes etc.)
        $codeResult = $this->verifyLoginCode($request, $member);
        if ($codeResult instanceof JsonResponse) {
            RateLimiter::hit($key, 60);

            return $codeResult;
        }

        $tokenName = $this->tokenScope().'-full';
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

        if (! $gym) {
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
        if (! app(LoginCodeVerifier::class)->checkIssuedCode($member, $request->code)) {
            return $this->invalidCodeResponse();
        }

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
        // Per-account cap, independent of the client IP. Answered like a sent
        // code: a distinct error would reveal that the account exists.
        if (! app(LoginCodeVerifier::class)->mayIssueCode($member)) {
            Log::warning('Login code not sent: hourly limit reached', [
                'member_id' => $member->id,
                'ip' => $request->ip(),
            ]);

            return $this->codeSentResponse();
        }

        $loginCode = LoginCode::createForMember($member);
        app(LoginCodeVerifier::class)->recordIssuedCode($member);
        $result = app(MemberMailDispatcher::class)->sendToMember($member, new LoginCodeMail($loginCode, $member, $gym));

        if ($result->wasSkipped()) {
            return response()->json([
                'success' => false,
                'message' => 'Für dieses Konto ist keine E-Mail-Adresse hinterlegt. Bitte wende dich an dein Studio.',
                'error_code' => $result->errorCode ?? 'MEMBER_NO_EMAIL',
            ], 422);
        }

        if ($result->hasFailed()) {
            return response()->json([
                'success' => false,
                'message' => 'Fehler beim Versenden der E-Mail. Bitte versuche es später erneut.',
                'error_code' => 'EMAIL_SEND_FAILED',
            ], 500);
        }

        RateLimiter::hit($rateLimitKey, 60);

        return $this->codeSentResponse();
    }

    /**
     * The one answer to every login code request, whether or not an account
     * exists for the address.
     */
    protected function codeSentResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Falls ein Konto zu dieser E-Mail-Adresse besteht, haben wir dir einen Anmeldecode geschickt.',
            'expires_in' => LoginCode::EXPIRY_MINUTES * 60,
        ]);
    }

    protected function invalidCodeResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ungültiger oder abgelaufener Code',
            'error_code' => 'INVALID_CODE',
        ], 422);
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
