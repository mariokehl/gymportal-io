<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Pwa\PwaAuthController;
use App\Http\Requests\Guest\GuestRegisterRequest;
use App\Models\Gym;
use App\Models\Member;
use App\Services\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class GuestAuthController extends PwaAuthController
{
    public function __construct(
        private GuestService $guestService
    ) {}

    protected function tokenScope(): string
    {
        return 'guest-pwa';
    }

    protected function tokenExpiration(Request $request): \DateTimeInterface
    {
        return now()->addDays(30);
    }

    protected function gymFilterScope(): array
    {
        return ['guest_enabled' => true];
    }

    protected function rateLimitPrefix(): string
    {
        return 'guest';
    }

    protected function memberResponseData(Member $member, Gym $gym): array
    {
        return [
            'id' => $member->id,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'birth_date' => $member->birth_date?->format('Y-m-d'),
            'age_verified' => $member->isAgeVerified(),
        ];
    }

    protected function gymResponseData(Gym $gym): array
    {
        return [
            'id' => $gym->id,
            'name' => $gym->getDisplayName(),
            'slug' => $gym->slug,
            'logo_url' => $gym->logo_url,
            'theme' => $gym->theme,
        ];
    }

    // ------------------------------------------------------------------
    // Guest-specific: registration endpoint
    // ------------------------------------------------------------------

    public function register(GuestRegisterRequest $request): JsonResponse
    {
        $email = strtolower($request->email);

        $key = 'guest-register:'.$request->ip().':'.$email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return $this->rateLimitedResponse();
        }

        $gym = $this->resolveGymOrFail($request);
        if ($gym instanceof JsonResponse) {
            return $gym;
        }

        $existingMember = $this->findMember($email, $gym);

        // An existing account is not revealed: it gets a login code instead
        // of a new account, and the answer is the same as for a registration.
        if ($existingMember) {
            if (! $this->isMemberEligible($existingMember)) {
                RateLimiter::hit($key, 60);

                return $this->codeSentResponse();
            }

            return $this->createAndSendCode($existingMember, $gym, $key, $request);
        }

        $member = $this->guestService->createGuestMember($gym, [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $email,
            'birth_date' => $request->birth_date,
        ]);

        return $this->createAndSendCode($member, $gym, $key, $request);
    }
}
