<?php

namespace Tests\Feature\Pwa;

use App\Models\Gym;
use App\Models\LoginCode;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Services\LoginCodeVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guessing a login code from many IP addresses must get no further than from
 * a single one: the limits are bound to the account, not the client.
 */
class LoginCodeBruteForceTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    private int $ip = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->gym = Gym::factory()->create(['guest_enabled' => true]);
        $this->member = Member::factory()->create([
            'gym_id' => $this->gym->id,
            'email' => 'test@example.com',
            'status' => 'active',
        ]);
    }

    /**
     * Every request comes from a fresh address, like a distributed attack.
     */
    private function fromNewIp(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$this->ip++]);
    }

    private function verify(string $code, string $prefix = 'pwa')
    {
        return $this->fromNewIp()->postJson("/api/{$prefix}/auth/verify-code", [
            'email' => 'test@example.com',
            'code' => $code,
            'gym_slug' => $this->gym->slug,
        ]);
    }

    private function wrongCode(LoginCode $loginCode): string
    {
        return $loginCode->code === '000000' ? '111111' : '000000';
    }

    #[Test]
    public function the_correct_code_still_works_within_the_limit(): void
    {
        $loginCode = LoginCode::createForMember($this->member);

        for ($i = 1; $i < LoginCodeVerifier::MAX_FAILED_ATTEMPTS; $i++) {
            $this->verify($this->wrongCode($loginCode))->assertStatus(422);
        }

        $this->verify($loginCode->code)->assertOk();
    }

    #[Test]
    public function a_code_is_discarded_after_too_many_wrong_entries_from_any_ip(): void
    {
        $loginCode = LoginCode::createForMember($this->member);

        for ($i = 0; $i < LoginCodeVerifier::MAX_FAILED_ATTEMPTS; $i++) {
            $this->verify($this->wrongCode($loginCode))->assertStatus(422);
        }

        // Even the right code no longer works; a new one has to be requested.
        $this->verify($loginCode->code)->assertStatus(422);
        $this->assertTrue($loginCode->fresh()->used);
    }

    #[Test]
    public function the_guest_login_discards_codes_the_same_way(): void
    {
        $this->member->update(['status' => 'extern']);
        $loginCode = LoginCode::createForMember($this->member);

        for ($i = 0; $i < LoginCodeVerifier::MAX_FAILED_ATTEMPTS; $i++) {
            $this->verify($this->wrongCode($loginCode), 'guests')->assertStatus(422);
        }

        $this->verify($loginCode->code, 'guests')->assertStatus(422);
    }

    #[Test]
    public function an_account_can_request_only_a_few_codes_per_hour(): void
    {
        for ($i = 0; $i < LoginCodeVerifier::MAX_CODES_PER_HOUR; $i++) {
            $this->fromNewIp()->postJson('/api/pwa/auth/send-code', [
                'email' => 'test@example.com',
                'gym_slug' => $this->gym->slug,
            ])->assertOk();
        }

        $this->fromNewIp()->postJson('/api/pwa/auth/send-code', [
            'email' => 'test@example.com',
            'gym_slug' => $this->gym->slug,
        ])->assertStatus(429);

        $this->assertSame(LoginCodeVerifier::MAX_CODES_PER_HOUR, LoginCode::where('member_id', $this->member->id)->count());
    }

    #[Test]
    public function the_static_code_locks_after_too_many_wrong_entries(): void
    {
        // Not mass assignable, set like the member page does.
        $config = new MemberAccessConfig(['member_id' => $this->member->id]);
        $config->static_login_code = '424242';
        $config->save();

        $this->verify('424242')->assertOk();

        for ($i = 0; $i < LoginCodeVerifier::MAX_STATIC_FAILURES_PER_HOUR; $i++) {
            $this->verify('000000')->assertStatus(422);
        }

        $this->verify('424242')->assertStatus(422);
    }
}
