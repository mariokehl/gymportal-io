<?php

namespace Tests\Feature\Pwa;

use App\Mail\LoginCodeMail;
use App\Models\Gym;
use App\Models\LoginCode;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear('login-code:127.0.0.1:test@example.com');
    }

    private function createGymWithMember(array $gymOverrides = [], array $memberOverrides = []): array
    {
        $gym = Gym::factory()->create($gymOverrides);
        $member = Member::factory()->create(array_merge([
            'gym_id' => $gym->id,
            'email' => 'test@example.com',
            'status' => 'active',
        ], $memberOverrides));

        return [$gym, $member];
    }

    // ------------------------------------------------------------------
    // sendLoginCode
    // ------------------------------------------------------------------

    #[Test]
    public function send_login_code_validates_required_fields(): void
    {
        $this->postJson('/api/pwa/auth/send-code', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'gym_slug']);
    }

    #[Test]
    public function send_login_code_returns_404_when_gym_does_not_exist(): void
    {
        $this->postJson('/api/pwa/auth/send-code', [
            'email' => 'test@example.com',
            'gym_slug' => 'unknown-gym',
        ])
            ->assertStatus(404)
            ->assertJson(['error_code' => 'GYM_NOT_FOUND']);
    }

    #[Test]
    public function send_login_code_returns_404_when_pwa_is_disabled_on_gym(): void
    {
        $gym = Gym::factory()->pwaDisabled()->create();

        $this->postJson('/api/pwa/auth/send-code', [
            'email' => 'foo@example.com',
            'gym_slug' => $gym->slug,
        ])
            ->assertStatus(404)
            ->assertJson(['error_code' => 'GYM_NOT_FOUND']);
    }

    #[Test]
    public function send_login_code_returns_403_when_pwa_login_disabled_for_pwa_client(): void
    {
        $gym = Gym::factory()->pwaLoginDisabled()->create();

        $this->postJson('/api/pwa/auth/send-code', [
            'email' => 'foo@example.com',
            'gym_slug' => $gym->slug,
        ], ['X-Client-Type' => 'pwa'])
            ->assertStatus(403)
            ->assertJson(['error_code' => 'PWA_LOGIN_DISABLED']);
    }

    #[Test]
    public function send_login_code_allows_branded_app_when_pwa_login_disabled(): void
    {
        [$gym, $member] = $this->createGymWithMember(['pwa_settings' => ['pwa_login_disabled' => true]]);

        $this->postJson('/api/pwa/auth/send-code', [
            'email' => $member->email,
            'gym_slug' => $gym->slug,
        ], ['X-Client-Type' => 'branded-app'])
            ->assertOk()
            ->assertJson(['success' => true]);

        Mail::assertSent(LoginCodeMail::class);
    }

    #[Test]
    public function send_login_code_returns_404_for_unknown_member(): void
    {
        $gym = Gym::factory()->create();

        $this->postJson('/api/pwa/auth/send-code', [
            'email' => 'nobody@example.com',
            'gym_slug' => $gym->slug,
        ])
            ->assertStatus(404)
            ->assertJson(['error_code' => 'MEMBER_NOT_FOUND']);
    }

    #[Test]
    public function send_login_code_returns_403_for_inactive_member(): void
    {
        [$gym, $member] = $this->createGymWithMember([], ['status' => 'inactive']);

        $this->postJson('/api/pwa/auth/send-code', [
            'email' => $member->email,
            'gym_slug' => $gym->slug,
        ])
            ->assertStatus(403)
            ->assertJson(['error_code' => 'MEMBER_INACTIVE']);
    }

    #[Test]
    public function send_login_code_creates_code_and_sends_mail_for_active_member(): void
    {
        [$gym, $member] = $this->createGymWithMember();

        $this->postJson('/api/pwa/auth/send-code', [
            'email' => $member->email,
            'gym_slug' => $gym->slug,
        ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'expires_in' => 600,
            ]);

        $this->assertDatabaseHas('login_codes', [
            'member_id' => $member->id,
            'used' => false,
        ]);
        Mail::assertSent(LoginCodeMail::class, fn ($m) => $m->hasTo($member->email));
    }

    #[Test]
    public function send_login_code_returns_success_without_sending_mail_for_static_code(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        MemberAccessConfig::factory()->create([
            'member_id' => $member->id,
            'static_login_code' => '111222',
        ]);

        $this->postJson('/api/pwa/auth/send-code', [
            'email' => $member->email,
            'gym_slug' => $gym->slug,
        ])
            ->assertOk()
            ->assertJson(['success' => true, 'expires_in' => 600]);

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('login_codes', ['member_id' => $member->id]);
    }

    #[Test]
    public function send_login_code_blocks_branded_app_at_device_limit(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        // hasReachedLimit defaults to >= 2 devices (env MAX_DEVICES_PER_MEMBER=2)
        MemberDevice::factory()->count(2)->create(['member_id' => $member->id]);

        $this->postJson('/api/pwa/auth/send-code', [
            'email' => $member->email,
            'gym_slug' => $gym->slug,
        ], [
            'X-Client-Type' => 'branded-app',
            'X-Device-Token' => 'new-device-token',
        ])
            ->assertStatus(403)
            ->assertJson(['error_code' => 'DEVICE_LIMIT_REACHED']);
    }

    #[Test]
    public function send_login_code_allows_already_registered_branded_device(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        MemberDevice::factory()->count(2)->create(['member_id' => $member->id]);
        $existing = MemberDevice::where('member_id', $member->id)->first();

        $this->postJson('/api/pwa/auth/send-code', [
            'email' => $member->email,
            'gym_slug' => $gym->slug,
        ], [
            'X-Client-Type' => 'branded-app',
            'X-Device-Token' => $existing->device_token,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    #[Test]
    public function send_login_code_rate_limits_after_three_attempts(): void
    {
        [$gym] = $this->createGymWithMember();
        $payload = ['email' => 'unknown@example.com', 'gym_slug' => $gym->slug];

        // Three not-found attempts
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/pwa/auth/send-code', $payload)->assertStatus(404);
        }

        $this->postJson('/api/pwa/auth/send-code', $payload)
            ->assertStatus(429)
            ->assertJson(['error_code' => 'RATE_LIMITED']);
    }

    // ------------------------------------------------------------------
    // verifyCode
    // ------------------------------------------------------------------

    #[Test]
    public function verify_code_validates_input(): void
    {
        $this->postJson('/api/pwa/auth/verify-code', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'code', 'gym_slug']);

        $this->postJson('/api/pwa/auth/verify-code', [
            'email' => 'test@example.com',
            'code' => 'short',
            'gym_slug' => 'foo',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function verify_code_returns_404_for_unknown_member(): void
    {
        $gym = Gym::factory()->create();

        $this->postJson('/api/pwa/auth/verify-code', [
            'email' => 'nobody@example.com',
            'code' => '123456',
            'gym_slug' => $gym->slug,
        ])
            ->assertStatus(404)
            ->assertJson(['error_code' => 'MEMBER_NOT_FOUND']);
    }

    #[Test]
    public function verify_code_returns_422_for_invalid_code(): void
    {
        [$gym, $member] = $this->createGymWithMember();

        $this->postJson('/api/pwa/auth/verify-code', [
            'email' => $member->email,
            'code' => '999999',
            'gym_slug' => $gym->slug,
        ])
            ->assertStatus(422)
            ->assertJson(['error_code' => 'INVALID_CODE']);
    }

    #[Test]
    public function verify_code_returns_token_and_marks_code_used(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        $loginCode = LoginCode::createForMember($member);

        $response = $this->postJson('/api/pwa/auth/verify-code', [
            'email' => $member->email,
            'code' => $loginCode->code,
            'gym_slug' => $gym->slug,
        ])
            ->assertOk()
            ->assertJsonStructure(['success', 'token', 'token_type', 'member', 'gym']);

        $this->assertSame('full', $response->json('token_type'));
        $this->assertTrue($loginCode->fresh()->used);
    }

    #[Test]
    public function verify_code_accepts_static_code_without_marking_used(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        MemberAccessConfig::factory()->create([
            'member_id' => $member->id,
            'static_login_code' => '424242',
        ]);

        $this->postJson('/api/pwa/auth/verify-code', [
            'email' => $member->email,
            'code' => '424242',
            'gym_slug' => $gym->slug,
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'full');

        $this->assertDatabaseMissing('login_codes', ['member_id' => $member->id]);
    }

    #[Test]
    public function verify_code_registers_device_for_branded_app(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        $loginCode = LoginCode::createForMember($member);

        $this->postJson('/api/pwa/auth/verify-code', [
            'email' => $member->email,
            'code' => $loginCode->code,
            'gym_slug' => $gym->slug,
        ], [
            'X-Client-Type' => 'branded-app',
            'X-Device-Token' => 'device-abc',
        ])->assertOk();

        $this->assertDatabaseHas('member_devices', [
            'member_id' => $member->id,
            'device_token' => 'device-abc',
        ]);
    }

    #[Test]
    public function verify_code_does_not_register_device_for_static_code(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        MemberAccessConfig::factory()->create([
            'member_id' => $member->id,
            'static_login_code' => '424242',
        ]);

        $this->postJson('/api/pwa/auth/verify-code', [
            'email' => $member->email,
            'code' => '424242',
            'gym_slug' => $gym->slug,
        ], [
            'X-Client-Type' => 'branded-app',
            'X-Device-Token' => 'device-abc',
        ])->assertOk();

        $this->assertDatabaseMissing('member_devices', ['member_id' => $member->id]);
    }

    #[Test]
    public function verify_code_rate_limits_after_five_attempts(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        $payload = [
            'email' => $member->email,
            'code' => '000000',
            'gym_slug' => $gym->slug,
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/pwa/auth/verify-code', $payload)->assertStatus(422);
        }

        $this->postJson('/api/pwa/auth/verify-code', $payload)
            ->assertStatus(429)
            ->assertJson(['error_code' => 'RATE_LIMITED']);
    }

    // ------------------------------------------------------------------
    // logout
    // ------------------------------------------------------------------

    #[Test]
    public function logout_revokes_current_token(): void
    {
        [, $member] = $this->createGymWithMember();
        $plain = $member->createToken('test', ['member-pwa', 'full'])->plainTextToken;
        [$tokenId] = explode('|', $plain, 2);

        $this->withHeader('Authorization', 'Bearer ' . $plain)
            ->postJson('/api/pwa/member/logout')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    #[Test]
    public function logout_requires_authentication(): void
    {
        $this->postJson('/api/pwa/member/logout')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // linkContractAnonymous
    // ------------------------------------------------------------------

    #[Test]
    public function link_contract_anonymous_validates_input(): void
    {
        $this->postJson('/api/pwa/auth/link-contract-anonymous', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'birth_date', 'gym_slug']);
    }

    #[Test]
    public function link_contract_anonymous_returns_404_when_no_match(): void
    {
        [$gym] = $this->createGymWithMember([], ['birth_date' => '1990-01-01']);

        $this->postJson('/api/pwa/auth/link-contract-anonymous', [
            'email' => 'test@example.com',
            'birth_date' => '1980-12-31',
            'gym_slug' => $gym->slug,
        ])->assertStatus(404);
    }

    #[Test]
    public function link_contract_anonymous_returns_anonymous_token(): void
    {
        [$gym, $member] = $this->createGymWithMember([], ['birth_date' => '1990-01-01']);

        $response = $this->postJson('/api/pwa/auth/link-contract-anonymous', [
            'email' => $member->email,
            'birth_date' => '1990-01-01',
            'gym_slug' => $gym->slug,
        ])->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'member'])
            ->assertJsonPath('token_type', 'anonymous');

        $this->assertNotEmpty($response->json('token'));
        // Returned member data is masked (no plain phone field)
        $this->assertArrayHasKey('phone_masked', $response->json('member'));
    }

    // ------------------------------------------------------------------
    // upgradeSession
    // ------------------------------------------------------------------

    #[Test]
    public function upgrade_session_requires_authentication(): void
    {
        $this->postJson('/api/pwa/member/upgrade-session', [
            'code' => '123456',
            'gym_slug' => 'foo',
        ])->assertStatus(401);
    }

    #[Test]
    public function upgrade_session_swaps_anonymous_token_for_full_token(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        $loginCode = LoginCode::createForMember($member);
        $anonPlain = $member->createToken('anon', ['member-pwa', 'anonymous'])->plainTextToken;
        [$anonId] = explode('|', $anonPlain, 2);

        $response = $this->withHeader('Authorization', 'Bearer ' . $anonPlain)
            ->postJson('/api/pwa/member/upgrade-session', [
                'code' => $loginCode->code,
                'gym_slug' => $gym->slug,
            ])
            ->assertOk()
            ->assertJsonPath('token_type', 'full');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $anonId]);
        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function upgrade_session_rejects_invalid_code(): void
    {
        [$gym, $member] = $this->createGymWithMember();
        $anonToken = $member->createToken('anon', ['member-pwa', 'anonymous'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $anonToken)
            ->postJson('/api/pwa/member/upgrade-session', [
                'code' => '000000',
                'gym_slug' => $gym->slug,
            ])
            ->assertStatus(422)
            ->assertJson(['error_code' => 'INVALID_CODE']);
    }

    #[Test]
    public function upgrade_session_rejects_wrong_gym(): void
    {
        [, $member] = $this->createGymWithMember();
        $otherGym = Gym::factory()->create();
        $anonToken = $member->createToken('anon', ['member-pwa', 'anonymous'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $anonToken)
            ->postJson('/api/pwa/member/upgrade-session', [
                'code' => '123456',
                'gym_slug' => $otherGym->slug,
            ])
            ->assertStatus(403)
            ->assertJson(['error_code' => 'INVALID_GYM']);
    }
}
