<?php

namespace Tests\Feature\Web;

use App\Mail\GymInvitationMail;
use App\Models\Gym;
use App\Models\GymInvitation;
use App\Models\GymUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the hybrid team-invitation flow: existing users are linked
 * immediately, new users receive an emailed signed acceptance link, and only
 * owners/admins of the gym may invite or manage invitations.
 */
class GymInvitationTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        Role::factory()->create(['name' => 'Member', 'slug' => 'member']);
        Mail::fake();
    }

    /**
     * @return array{0: User, 1: Gym}
     */
    private function ownerWithGym(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return [$owner->fresh(), $gym];
    }

    #[Test]
    public function inviting_a_new_email_creates_a_pending_invitation_and_sends_mail(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('settings.gym-invitations.store'), [
                'email' => 'NEW@example.com',
                'role' => 'trainer',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gym_invitations', [
            'gym_id' => $gym->id,
            'email' => 'new@example.com', // normalized to lowercase
            'role' => 'trainer',
        ]);

        Mail::assertSent(GymInvitationMail::class, function (GymInvitationMail $mail) {
            return $mail->hasTo('new@example.com');
        });
    }

    #[Test]
    public function inviting_an_existing_user_links_them_immediately_without_mail(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $this->actingAs($owner)
            ->post(route('settings.gym-invitations.store'), [
                'email' => 'existing@example.com',
                'role' => 'staff',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gym_users', [
            'gym_id' => $gym->id,
            'user_id' => $existing->id,
            'role' => 'staff',
        ]);
        $this->assertDatabaseMissing('gym_invitations', ['email' => 'existing@example.com']);
        Mail::assertNothingSent();
    }

    #[Test]
    public function inviting_an_existing_member_is_rejected(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $existing = User::factory()->create(['email' => 'member@example.com']);
        GymUser::create(['gym_id' => $gym->id, 'user_id' => $existing->id, 'role' => 'staff']);

        $this->actingAs($owner)
            ->post(route('settings.gym-invitations.store'), [
                'email' => 'member@example.com',
                'role' => 'trainer',
            ])
            ->assertSessionHas('error');

        // Role must not have changed via a duplicate insert.
        $this->assertDatabaseHas('gym_users', [
            'gym_id' => $gym->id,
            'user_id' => $existing->id,
            'role' => 'staff',
        ]);
    }

    #[Test]
    public function duplicate_pending_invitation_is_rejected(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        GymInvitation::create(['gym_id' => $gym->id, 'email' => 'dup@example.com', 'role' => 'staff']);

        $this->actingAs($owner)
            ->post(route('settings.gym-invitations.store'), [
                'email' => 'dup@example.com',
                'role' => 'trainer',
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, GymInvitation::where('email', 'dup@example.com')->count());
    }

    #[Test]
    public function staff_member_may_not_invite(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $staff = User::factory()->create();
        GymUser::create(['gym_id' => $gym->id, 'user_id' => $staff->id, 'role' => 'staff']);
        $staff->update(['current_gym_id' => $gym->id]);

        $this->actingAs($staff->fresh())
            ->post(route('settings.gym-invitations.store'), [
                'email' => 'someone@example.com',
                'role' => 'trainer',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('gym_invitations', ['email' => 'someone@example.com']);
    }

    #[Test]
    public function accepting_a_signed_invitation_as_an_existing_logged_in_user_links_membership(): void
    {
        [, $gym] = $this->ownerWithGym();
        $invitee = User::factory()->create(['email' => 'join@example.com']);
        $invitation = GymInvitation::create([
            'gym_id' => $gym->id,
            'email' => 'join@example.com',
            'role' => 'trainer',
        ]);

        $url = URL::signedRoute('gym-invitations.accept', [
            'invitation' => $invitation->id,
            'token' => $invitation->token,
        ]);

        $this->actingAs($invitee)
            ->get($url)
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('gym_users', [
            'gym_id' => $gym->id,
            'user_id' => $invitee->id,
            'role' => 'trainer',
        ]);
        $this->assertDatabaseMissing('gym_invitations', ['id' => $invitation->id]);
        $this->assertSame($gym->id, $invitee->fresh()->current_gym_id);
    }

    #[Test]
    public function accepting_for_a_brand_new_user_creates_account_and_membership(): void
    {
        [, $gym] = $this->ownerWithGym();
        $invitation = GymInvitation::create([
            'gym_id' => $gym->id,
            'email' => 'brandnew@example.com',
            'role' => 'staff',
        ]);

        $url = URL::signedRoute('gym-invitations.accept', [
            'invitation' => $invitation->id,
            'token' => $invitation->token,
        ]);

        $this->get($url)->assertRedirect(route('login'));

        $newUser = User::where('email', 'brandnew@example.com')->first();
        $this->assertNotNull($newUser, 'A new account must be created for the invitee.');
        $this->assertNotNull($newUser->email_verified_at);
        $this->assertDatabaseHas('gym_users', [
            'gym_id' => $gym->id,
            'user_id' => $newUser->id,
            'role' => 'staff',
        ]);
        $this->assertDatabaseMissing('gym_invitations', ['id' => $invitation->id]);
    }

    #[Test]
    public function accept_with_a_wrong_token_is_forbidden(): void
    {
        [, $gym] = $this->ownerWithGym();
        $invitation = GymInvitation::create([
            'gym_id' => $gym->id,
            'email' => 'join@example.com',
            'role' => 'trainer',
        ]);

        // Sign the route but tamper with the token query parameter.
        $url = URL::signedRoute('gym-invitations.accept', [
            'invitation' => $invitation->id,
            'token' => 'wrong-token',
        ]);

        $this->get($url)->assertForbidden();
        $this->assertDatabaseHas('gym_invitations', ['id' => $invitation->id]);
    }

    #[Test]
    public function unsigned_accept_link_is_rejected(): void
    {
        [, $gym] = $this->ownerWithGym();
        $invitation = GymInvitation::create([
            'gym_id' => $gym->id,
            'email' => 'join@example.com',
            'role' => 'trainer',
        ]);

        $this->get(route('gym-invitations.accept', [
            'invitation' => $invitation->id,
            'token' => $invitation->token,
        ]))->assertForbidden();
    }

    #[Test]
    public function owner_can_withdraw_a_pending_invitation(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $invitation = GymInvitation::create([
            'gym_id' => $gym->id,
            'email' => 'gone@example.com',
            'role' => 'staff',
        ]);

        $this->actingAs($owner)
            ->delete(route('settings.gym-invitations.destroy', $invitation->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('gym_invitations', ['id' => $invitation->id]);
    }

    #[Test]
    public function invitation_mail_renders_gym_role_and_accept_url(): void
    {
        [, $gym] = $this->ownerWithGym();
        $invitation = GymInvitation::create([
            'gym_id' => $gym->id,
            'email' => 'render@example.com',
            'role' => 'trainer',
        ]);

        $mail = new GymInvitationMail($invitation, 'https://example.test/accept-link');
        $rendered = $mail->render();

        $this->assertStringContainsString($gym->getDisplayName(), $rendered);
        $this->assertStringContainsString('Trainer', $rendered);
        $this->assertStringContainsString('example.test/accept-link', $rendered);
    }

    #[Test]
    public function owner_can_resend_a_pending_invitation(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $invitation = GymInvitation::create([
            'gym_id' => $gym->id,
            'email' => 'again@example.com',
            'role' => 'staff',
        ]);

        $this->actingAs($owner)
            ->post(route('settings.gym-invitations.resend', $invitation->id))
            ->assertRedirect();

        Mail::assertSent(GymInvitationMail::class);
    }
}
