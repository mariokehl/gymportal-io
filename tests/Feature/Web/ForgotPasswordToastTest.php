<?php

namespace Tests\Feature\Web;

use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ForgotPasswordToastTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Falls ein Konto mit dieser E-Mail-Adresse existiert, haben wir Ihnen einen Link zum Zurücksetzen des Passworts gesendet.';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    #[Test]
    public function requesting_a_reset_link_shows_a_success_toast(): void
    {
        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $user = User::factory()->create(['role_id' => $roleId]);

        $this->requestResetLink($user->email);

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertToastShown();
    }

    #[Test]
    public function an_unknown_address_gets_the_same_answer_as_a_known_one(): void
    {
        $this->requestResetLink('unknown@example.com')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
        $this->assertToastShown();
    }

    private function requestResetLink(string $email)
    {
        return $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $email])
            ->assertRedirect(route('password.request'));
    }

    private function assertToastShown(): void
    {
        $this->get(route('password.request'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ForgotPassword')
                ->hasFlash('toasts', [['type' => 'success', 'message' => self::MESSAGE]]));
    }
}
