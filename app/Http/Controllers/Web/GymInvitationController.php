<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreGymInvitationRequest;
use App\Mail\GymInvitationMail;
use App\Models\GymInvitation;
use App\Models\GymUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class GymInvitationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Invite a user to the current gym's team.
     *
     * Hybrid flow: if the email already belongs to a user, link them to the gym
     * immediately. Otherwise create a pending invitation and email a signed
     * acceptance link.
     */
    public function store(StoreGymInvitationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $gym = $user->currentGym;

        if (! $gym) {
            return back()->with('error', 'Kein Gym gefunden.');
        }

        $this->authorize('manage', $gym);

        $validated = $request->validated();
        $email = $validated['email'];

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $alreadyMember = GymUser::where('gym_id', $gym->id)
                ->where('user_id', $existingUser->id)
                ->exists();

            if ($alreadyMember || $existingUser->id === $gym->owner_id) {
                return back()->with('error', 'Dieser Benutzer ist bereits Teil des Teams.');
            }

            GymUser::create([
                'gym_id' => $gym->id,
                'user_id' => $existingUser->id,
                'role' => $validated['role'],
            ]);

            return back()->with('success', 'Benutzer wurde dem Team hinzugefügt.');
        }

        $pendingExists = GymInvitation::where('gym_id', $gym->id)
            ->where('email', $email)
            ->exists();

        if ($pendingExists) {
            return back()->with('error', 'Für diese E-Mail-Adresse besteht bereits eine Einladung.');
        }

        $invitation = GymInvitation::create([
            'gym_id' => $gym->id,
            'email' => $email,
            'role' => $validated['role'],
            'invited_by' => $user->id,
        ]);

        $this->sendInvitationMail($invitation);

        return back()->with('success', 'Einladung wurde versendet.');
    }

    /**
     * Resend the invitation email for a pending invitation.
     */
    public function resend(GymInvitation $invitation): RedirectResponse
    {
        $this->authorize('manage', $invitation->gym);

        $this->sendInvitationMail($invitation);

        return back()->with('success', 'Einladung wurde erneut versendet.');
    }

    /**
     * Withdraw a pending invitation.
     */
    public function destroy(GymInvitation $invitation): RedirectResponse
    {
        $this->authorize('manage', $invitation->gym);

        $invitation->delete();

        return back()->with('success', 'Einladung wurde zurückgezogen.');
    }

    /**
     * Accept an invitation via the signed link from the email.
     *
     * The route is signed; we additionally match the opaque token. Existing
     * users are linked immediately (logging in first if necessary); new users
     * get an account and are routed through the password-setup (reset) flow.
     */
    public function accept(Request $request, GymInvitation $invitation): RedirectResponse
    {
        if ($request->query('token') !== $invitation->token) {
            abort(403);
        }

        if ($invitation->isExpired()) {
            $invitation->delete();

            return redirect()->route('login')
                ->with('error', 'Diese Einladung ist abgelaufen.');
        }

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            return $this->acceptForExistingUser($request, $invitation, $existingUser);
        }

        return $this->acceptForNewUser($invitation);
    }

    /**
     * Link an existing account to the gym. If the visitor is not the invited
     * user (or not logged in), send them to login and resume afterwards.
     */
    private function acceptForExistingUser(Request $request, GymInvitation $invitation, User $existingUser): RedirectResponse
    {
        if (Auth::id() !== $existingUser->id) {
            // Preserve the signed acceptance URL so the user lands back here
            // after authenticating.
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login')
                ->with('message', 'Bitte melden Sie sich an, um die Einladung anzunehmen.');
        }

        $this->attachMember($invitation, $existingUser);
        $invitation->delete();

        $existingUser->update(['current_gym_id' => $invitation->gym_id]);

        return redirect()->route('dashboard')
            ->with('success', 'Sie sind dem Team von '.$invitation->gym->getDisplayName().' beigetreten.');
    }

    /**
     * Create the account for a brand new invitee and route them through the
     * password-setup flow (reused password reset), then attach the membership.
     */
    private function acceptForNewUser(GymInvitation $invitation): RedirectResponse
    {
        $memberRole = Role::where('slug', 'member')->first();

        $newUser = User::create([
            'first_name' => 'Team',
            'last_name' => 'Mitglied',
            'email' => $invitation->email,
            'password' => Hash::make(Str::random(40)),
            'role_id' => $memberRole?->id,
        ]);

        // email_verified_at is guarded; the invitation already proves ownership
        // of the inbox, so mark it verified explicitly.
        $newUser->forceFill(['email_verified_at' => now()])->save();

        $this->attachMember($invitation, $newUser);
        $newUser->update(['current_gym_id' => $invitation->gym_id]);

        $invitation->delete();

        // Let the user choose their own password via the standard reset flow.
        Password::sendResetLink(['email' => $newUser->email]);

        return redirect()->route('login')
            ->with('message', 'Ihr Konto wurde erstellt. Wir haben Ihnen eine E-Mail zum Festlegen Ihres Passworts gesendet.');
    }

    private function attachMember(GymInvitation $invitation, User $user): void
    {
        $alreadyMember = GymUser::where('gym_id', $invitation->gym_id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $alreadyMember) {
            GymUser::create([
                'gym_id' => $invitation->gym_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);
        }
    }

    private function sendInvitationMail(GymInvitation $invitation): void
    {
        $acceptUrl = URL::signedRoute('gym-invitations.accept', [
            'invitation' => $invitation->id,
            'token' => $invitation->token,
        ]);

        // Send synchronously so the invitation arrives immediately, without
        // depending on a queue worker being available.
        Mail::to($invitation->email)->send(new GymInvitationMail($invitation, $acceptUrl));
    }
}
