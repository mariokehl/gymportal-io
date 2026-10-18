<?php

namespace Tests\Feature\Guest;

use App\Mail\LoginCodeMail;
use App\Models\Gym;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Registration and login of the guest app must not reveal whether an
 * account exists for an email address.
 */
class GuestAuthEnumerationTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $guest;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->gym = Gym::factory()->create(['guest_enabled' => true]);
        $this->guest = Member::factory()->create([
            'gym_id' => $this->gym->id,
            'email' => 'known@example.com',
            'status' => 'extern',
        ]);
    }

    private function sendCode(string $email)
    {
        return $this->postJson('/api/guests/auth/send-code', [
            'email' => $email,
            'gym_slug' => $this->gym->slug,
        ]);
    }

    private function register(string $email)
    {
        return $this->postJson('/api/guests/auth/register', [
            'first_name' => 'Erika',
            'last_name' => 'Muster',
            'birth_date' => '1990-01-01',
            'email' => $email,
            'gym_slug' => $this->gym->slug,
        ]);
    }

    #[Test]
    public function requesting_a_code_answers_the_same_for_known_and_unknown_addresses(): void
    {
        $known = $this->sendCode('known@example.com')->assertOk()->json();
        $unknown = $this->sendCode('unknown@example.com')->assertOk()->json();

        $this->assertSame($known, $unknown);
        Mail::assertSent(LoginCodeMail::class, 1);
    }

    #[Test]
    public function an_ineligible_account_gets_the_same_answer_but_no_code(): void
    {
        $this->guest->update(['status' => 'blocked']);

        $answer = $this->sendCode('known@example.com')->assertOk()->json();

        $this->assertSame($this->sendCode('unknown@example.com')->json(), $answer);
        Mail::assertNothingSent();
    }

    #[Test]
    public function registering_a_known_address_sends_a_login_code_instead_of_failing(): void
    {
        $existing = $this->register('known@example.com')->assertOk()->json();
        $new = $this->register('new@example.com')->assertOk()->json();

        $this->assertSame($existing, $new);
        // No second account for the known address, but a code to log in.
        $this->assertSame(1, Member::where('email', 'known@example.com')->count());
        Mail::assertSent(LoginCodeMail::class, fn (LoginCodeMail $mail) => $mail->hasTo('known@example.com'));
    }

    #[Test]
    public function verifying_a_code_for_an_unknown_address_looks_like_a_wrong_code(): void
    {
        $unknown = $this->postJson('/api/guests/auth/verify-code', [
            'email' => 'unknown@example.com',
            'code' => '123456',
            'gym_slug' => $this->gym->slug,
        ])->assertStatus(422)->json();

        $wrong = $this->postJson('/api/guests/auth/verify-code', [
            'email' => 'known@example.com',
            'code' => '123456',
            'gym_slug' => $this->gym->slug,
        ])->assertStatus(422)->json();

        $this->assertSame($wrong, $unknown);
    }
}
