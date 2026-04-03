<?php

namespace App\Http\Controllers\Pwa;

use App\Models\Gym;
use App\Models\LoginCode;
use App\Models\Member;
use App\Models\MemberDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends PwaAuthController
{
    protected function tokenScope(): string
    {
        return 'member-pwa';
    }

    protected function tokenExpiration(Request $request): \DateTimeInterface
    {
        $deviceToken = $request->header('X-Device-Token');

        if ($this->isBrandedAppRequest($request) && $deviceToken) {
            return now()->addDays(90);
        }

        return now()->addDays(7);
    }

    protected function gymFilterScope(): array
    {
        return ['pwa_enabled' => true];
    }

    protected function rateLimitPrefix(): string
    {
        return 'pwa';
    }

    protected function memberResponseData(Member $member, Gym $gym): array
    {
        return $this->getFullMemberData($member, $gym);
    }

    protected function gymResponseData(Gym $gym): array
    {
        return $gym->getMemberAppData();
    }

    // ------------------------------------------------------------------
    // PWA-specific: resolve gym with login-disabled check
    // ------------------------------------------------------------------

    protected function isMemberEligible(Member $member): bool
    {
        return $member->status === 'active';
    }

    protected function resolveGymOrFail(Request $request): Gym|JsonResponse
    {
        $result = parent::resolveGymOrFail($request);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        if ($result->isPwaLoginDisabled() && $this->isPwaRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Der Login über die PWA ist momentan deaktiviert.',
                'error_code' => 'PWA_LOGIN_DISABLED',
                'app_store_links' => $result->getAppStoreLinks(),
            ], 403);
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // PWA-specific hooks: static login codes & device limiting
    // ------------------------------------------------------------------

    protected function beforeSendLoginCode(Request $request, Member $member, Gym $gym): ?JsonResponse
    {
        // Static login code (App Store review)
        if ($member->accessConfig && $member->accessConfig->hasStaticLoginCode()) {
            RateLimiter::hit('pwa-login:' . $request->ip() . ':' . strtolower($request->email), 60);

            Log::info('Static login code requested (no email sent)', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Anmeldecode wurde versendet',
                'expires_in' => 600,
            ]);
        }

        // Device limit for branded apps
        if ($this->isBrandedAppRequest($request)) {
            $deviceToken = $request->header('X-Device-Token');

            if ($deviceToken) {
                $existingDevice = MemberDevice::where('device_token', $deviceToken)
                    ->where('member_id', $member->id)
                    ->first();

                if (!$existingDevice && MemberDevice::hasReachedLimit($member->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Dein Account ist bereits auf einem anderen Gerät aktiv. Aus Sicherheitsgründen kann die App nur auf einem Gerät gleichzeitig genutzt werden. Wenn du das Gerät wechseln möchtest, melde dich bitte kurz bei deinem Studio.',
                        'error_code' => 'DEVICE_LIMIT_REACHED',
                    ], 403);
                }
            }
        }

        return null;
    }

    protected function verifyLoginCode(Request $request, Member $member): ?JsonResponse
    {
        // Static login code check
        if ($member->accessConfig && $member->accessConfig->hasStaticLoginCode()) {
            if ($request->code !== $member->accessConfig->static_login_code) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ungültiger oder abgelaufener Code',
                    'error_code' => 'INVALID_CODE',
                ], 422);
            }

            return null; // static code valid
        }

        return parent::verifyLoginCode($request, $member);
    }

    protected function afterVerifyCode(Request $request, Member $member, Gym $gym): void
    {
        $hasStaticCode = $member->accessConfig && $member->accessConfig->hasStaticLoginCode();

        if ($this->isBrandedAppRequest($request) && !$hasStaticCode) {
            $deviceToken = $request->header('X-Device-Token');

            if ($deviceToken) {
                MemberDevice::registerForMember(
                    $member->id,
                    $deviceToken,
                    $request->ip(),
                    $request->userAgent()
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // PWA-specific endpoints: anonymous link & session upgrade
    // ------------------------------------------------------------------

    public function linkContractAnonymous(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'birth_date' => 'required|date',
            'gym_slug' => 'required|string',
        ]);

        $email = strtolower($request->email);

        $key = 'link-contract:' . $request->ip() . ':' . $email;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->rateLimitedResponse();
        }

        $gym = $this->resolveGymOrFail($request);
        if ($gym instanceof JsonResponse) {
            return $gym;
        }

        $member = Member::whereRaw('LOWER(email) = ?', [$email])
            ->where('gym_id', $gym->id)
            ->whereDate('birth_date', $request->birth_date)
            ->first();

        if (!$member) {
            RateLimiter::hit($key, 300);

            return response()->json([
                'message' => 'Kein Vertrag mit dieser E-Mail und Geburtsdatum gefunden.',
            ], 404);
        }

        $token = $member->createToken(
            'member-pwa-anonymous',
            ['member-pwa', 'anonymous'],
            now()->addDays(365)
        )->plainTextToken;

        RateLimiter::clear($key);

        Log::info('Member linked contract anonymously', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'token_type' => 'anonymous',
            'member' => $this->getMaskedMemberData($member, $gym),
        ]);
    }

    public function upgradeSession(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'gym_slug' => 'required|string',
        ]);

        /** @var Member $member */
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Nicht authentifiziert',
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $key = 'upgrade-session:' . $request->ip() . ':' . $member->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Zu viele fehlgeschlagene Versuche. Bitte warte.',
                'error_code' => 'RATE_LIMITED',
            ], 429);
        }

        $gym = Gym::where('slug', $request->gym_slug)
            ->where('pwa_enabled', true)
            ->first();

        if (!$gym) {
            return response()->json([
                'success' => false,
                'message' => 'Gym nicht gefunden',
                'error_code' => 'GYM_NOT_FOUND',
            ], 404);
        }

        if ($member->gym_id !== $gym->id) {
            return response()->json([
                'success' => false,
                'message' => 'Ungültige Gym-Zuordnung',
                'error_code' => 'INVALID_GYM',
            ], 403);
        }

        $codeResult = $this->verifyLoginCode($request, $member);
        if ($codeResult instanceof JsonResponse) {
            RateLimiter::hit($key, 60);

            return $codeResult;
        }

        $member->currentAccessToken()->delete();

        $token = $member->createToken(
            'member-pwa-full',
            ['member-pwa', 'full'],
            $this->tokenExpiration($request)
        )->plainTextToken;

        RateLimiter::clear($key);

        Log::info('Member upgraded to full session', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'token_type' => 'full',
            'member' => $this->getFullMemberData($member, $gym),
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function isPwaRequest(Request $request): bool
    {
        return $request->header('X-Client-Type') === 'pwa';
    }

    private function isBrandedAppRequest(Request $request): bool
    {
        return $request->header('X-Client-Type') === 'branded-app';
    }

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
                'slug' => $gym->slug,
            ],
            'is_verified' => false,
        ];
    }

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
            'birth_date' => $member->birth_date?->format('Y-m-d'),
            'status' => $member->status,
            'avatar_url' => null,
            'joined_date' => $member->joined_date?->format('Y-m-d'),
            'gym' => [
                'id' => $gym->id,
                'name' => $gym->name,
                'slug' => $gym->slug,
            ],
            'is_verified' => true,
        ];
    }
}
