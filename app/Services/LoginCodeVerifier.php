<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Checks login codes of the member and guest apps.
 *
 * The per-IP rate limits in the controllers only slow down a single client.
 * The limits here are bound to the account, so guessing a code from many
 * addresses in parallel gets no further than from one.
 */
class LoginCodeVerifier
{
    /**
     * Wrong entries after which an issued code is discarded.
     */
    public const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Codes an account can request per hour. Without this cap, requesting a
     * fresh code would reset the attempt limit over and over.
     */
    public const MAX_CODES_PER_HOUR = 5;

    /**
     * Wrong entries of a static login code per account and hour. The static
     * code never expires, so it needs its own cap.
     */
    public const MAX_STATIC_FAILURES_PER_HOUR = 10;

    public function mayIssueCode(Member $member): bool
    {
        return ! RateLimiter::tooManyAttempts($this->issueKey($member), self::MAX_CODES_PER_HOUR);
    }

    public function recordIssuedCode(Member $member): void
    {
        RateLimiter::hit($this->issueKey($member), 3600);
    }

    /**
     * Check a code sent by email. A correct code is used up; a wrong entry
     * counts against the member's current code and discards it at the limit.
     */
    public function checkIssuedCode(Member $member, string $code): bool
    {
        return DB::transaction(function () use ($member, $code) {
            $loginCode = LoginCode::forMember($member->id)
                ->valid()
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $loginCode) {
                return false;
            }

            if (hash_equals($loginCode->code, $code)) {
                $loginCode->markAsUsed();

                return true;
            }

            $attempts = $loginCode->failed_attempts + 1;
            $discard = $attempts >= self::MAX_FAILED_ATTEMPTS;

            $loginCode->update([
                'failed_attempts' => $attempts,
                'used' => $discard ? true : $loginCode->used,
            ]);

            if ($discard) {
                Log::warning('Login code discarded after too many wrong entries', [
                    'member_id' => $member->id,
                    'ip' => request()?->ip(),
                ]);
            }

            return false;
        });
    }

    /**
     * Check the static login code (App Store review accounts).
     */
    public function checkStaticCode(Member $member, string $code): bool
    {
        $key = 'login-static-failures:'.$member->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_STATIC_FAILURES_PER_HOUR)) {
            return false;
        }

        if (hash_equals((string) $member->accessConfig?->static_login_code, $code)) {
            return true;
        }

        RateLimiter::hit($key, 3600);

        return false;
    }

    private function issueKey(Member $member): string
    {
        return 'login-code-issued:'.$member->id;
    }
}
