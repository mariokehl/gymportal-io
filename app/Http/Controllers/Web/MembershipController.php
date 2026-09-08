<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\CancellationConfirmationMail;
use App\Mail\Dispatching\MemberMailDispatcher;
use App\Models\Addon;
use App\Models\Member;
use App\Models\Membership;
use App\Services\MemberService;
use App\Services\MembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private MemberService $memberService,
        private MemberMailDispatcher $mailDispatcher,
    ) {}

    /**
     * Creates a free period (e.g. a trial session).
     */
    public function storeFreePeriod(Request $request, Member $member)
    {
        $this->authorize('create', Membership::class);

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'linked_membership_id' => 'nullable|exists:memberships,id',
        ], [
            'start_date.required' => 'Das Startdatum ist erforderlich.',
            'end_date.required' => 'Das Enddatum ist erforderlich.',
            'end_date.after_or_equal' => 'Das Enddatum muss nach dem Startdatum liegen.',
        ]);

        // Gym of the current user
        $gym = auth()->user()->gym;

        DB::beginTransaction();
        try {
            // Check the linked membership (if one was given)
            $linkedMembership = null;
            if ($validated['linked_membership_id']) {
                $linkedMembership = Membership::where('id', $validated['linked_membership_id'])
                    ->where('member_id', $member->id)
                    ->first();

                if (! $linkedMembership) {
                    return back()->withErrors([
                        'linked_membership_id' => 'Die ausgewählte Mitgliedschaft gehört nicht zu diesem Mitglied.',
                    ]);
                }
            }

            // Create the free membership
            $freeMembership = $this->memberService->createFreePeriodMembership(
                $member,
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date']),
                $linkedMembership
            );

            // Store the link in the opposite direction as well
            if ($linkedMembership) {
                $linkedMembership->update([
                    'linked_free_membership_id' => $freeMembership->id,
                ]);
            }

            // The free period now governs the access period, so a standing guest access
            // would silently keep granting unlimited entry beyond it.
            $guestAccessRevoked = $member->hasGuestAccess();
            if ($guestAccessRevoked) {
                $member->revokeGuestAccess();
            }

            DB::commit();

            return back()->with('success', $guestAccessRevoked
                ? 'Der kostenlose Zeitraum wurde erfolgreich erstellt. Der Gastzugang wurde entzogen.'
                : 'Der kostenlose Zeitraum wurde erfolgreich erstellt.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'Der kostenlose Zeitraum konnte nicht erstellt werden: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Activates a pending membership.
     */
    public function activate(Request $request, Member $member, Membership $membership)
    {
        $this->authorize('update', $membership);

        // Check that the membership belongs to this member
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Check that the membership can be activated
        if ($membership->status !== 'pending') {
            return back()->withErrors([
                'status' => 'Nur ausstehende Mitgliedschaften können aktiviert werden.',
            ]);
        }

        // A paid membership needs a usable payment method
        $blockReason = $membership->getActivationBlockReason();

        if ($blockReason !== null) {
            return back()->withErrors([
                'payment_method' => $blockReason,
            ]);
        }

        DB::beginTransaction();
        try {
            // Activate the membership (dispatches MembershipActivated, which creates the contract)
            $membership->activateMembership();

            // Activate the member too, in case it is still pending
            if ($member->status === 'pending') {
                $member->update(['status' => 'active']);
            }

            // Append a note
            $membership->update([
                'notes' => ($membership->notes ? $membership->notes."\n" : '').
                          'Manuell aktiviert am '.now()->format('d.m.Y H:i'),
            ]);

            DB::commit();

            return back()->with('success', 'Die Mitgliedschaft wurde erfolgreich aktiviert.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'Die Mitgliedschaft konnte nicht aktiviert werden: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Pauses a membership, or schedules the pause for a later start date.
     */
    public function pause(
        Request $request,
        Member $member,
        Membership $membership,
        MembershipService $membershipService,
    ) {
        $this->authorize('update', $membership);

        // Check that the membership belongs to this member
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Validation
        $validated = $request->validate([
            'pause_start_date' => 'required|date|after_or_equal:today',
            'pause_end_date' => 'required|date|after:pause_start_date',
            'reason' => 'nullable|string|max:500',
        ], [
            'pause_start_date.required' => 'Das Startdatum ist erforderlich.',
            'pause_start_date.after_or_equal' => 'Das Startdatum muss heute oder in der Zukunft liegen.',
            'pause_end_date.required' => 'Das Enddatum ist erforderlich.',
            'pause_end_date.after' => 'Das Enddatum muss nach dem Startdatum liegen.',
        ]);

        $eligibility = $membershipService->checkPauseEligibility($membership);

        if (! $eligibility['eligible']) {
            return back()->withErrors([
                'status' => $eligibility['reason'],
            ]);
        }

        try {
            $membership = $membershipService->pause(
                $membership,
                $validated['pause_start_date'],
                $validated['pause_end_date'],
                $validated['reason'] ?? null,
            );
        } catch (\Exception $e) {
            Log::error('Pausing the membership failed', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Die Mitgliedschaft konnte nicht pausiert werden: '.$e->getMessage(),
            ]);
        }

        $successMessage = $membership->status === 'paused'
            ? 'Die Mitgliedschaft wurde erfolgreich pausiert.'
            : 'Die Pausierung wurde für den '.
              $membership->pause_start_date->format('d.m.Y').
              ' eingeplant. Die Mitgliedschaft bleibt bis dahin aktiv.';

        return back()->with('success', $successMessage);
    }

    /**
     * Resumes a paused membership.
     */
    public function resume(
        Request $request,
        Member $member,
        Membership $membership,
        MembershipService $membershipService,
    ) {
        $this->authorize('update', $membership);

        // Check that the membership belongs to this member
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        $eligibility = $membershipService->checkResumeEligibility($membership);

        if (! $eligibility['eligible']) {
            return back()->withErrors([
                'status' => $eligibility['reason'],
            ]);
        }

        try {
            $membershipService->resume($membership);
        } catch (\Exception $e) {
            Log::error('Resuming the membership failed', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Die Mitgliedschaft konnte nicht wieder aufgenommen werden: '.$e->getMessage(),
            ]);
        }

        return back()->with('success', 'Die Mitgliedschaft wurde erfolgreich wieder aufgenommen.');
    }

    /**
     * Cancels a membership.
     */
    public function cancel(Request $request, Member $member, Membership $membership)
    {
        $this->authorize('update', $membership);

        // Validation
        $validated = $request->validate([
            'cancellation_date' => 'required|date|after_or_equal:today',
            'cancellation_reason' => 'required|string|in:move,financial,health,dissatisfied,no_time,other',
            'cancellation_type' => 'nullable|in:ordinary,extraordinary',
            'cancellation_reason_note' => 'nullable|string|max:255',
            'send_confirmation' => 'boolean',
            'immediate' => 'boolean',
        ], [
            'cancellation_date.required' => 'Das Kündigungsdatum ist erforderlich.',
            'cancellation_date.after_or_equal' => 'Das Kündigungsdatum muss heute oder in der Zukunft liegen.',
            'cancellation_reason.required' => 'Der Kündigungsgrund ist erforderlich.',
        ]);

        // Check that the membership belongs to this member
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Check that the membership can be cancelled
        if (! in_array($membership->status, ['active', 'paused'])) {
            return back()->withErrors([
                'status' => 'Diese Mitgliedschaft kann nicht gekündigt werden.',
            ]);
        }

        // Check that no cancellation exists yet
        if ($membership->cancellation_date) {
            return back()->withErrors([
                'cancellation' => 'Diese Mitgliedschaft wurde bereits gekündigt.',
            ]);
        }

        // An extraordinary cancellation is issued for cause and therefore ignores
        // both the commitment period and the notice period. Immediate
        // cancellations are always extraordinary.
        $isExtraordinary = $request->input('cancellation_type') === 'extraordinary'
            || $request->boolean('immediate');

        // Check the commitment period (skipped for an extraordinary cancellation)
        if (! $isExtraordinary) {
            // The cancellation date is the last day of the membership, whereas the
            // commitment boundary is exclusive — so the day before it already
            // completes the commitment period in full.
            if ($membership->membershipPlan->commitment_months) {
                $minEndDate = Carbon::parse($membership->start_date)
                    ->addMonths($membership->membershipPlan->commitment_months)
                    ->subDay();

                if (Carbon::parse($validated['cancellation_date'])->lt($minEndDate)) {
                    return back()->withErrors([
                        'cancellation_date' => 'Das Kündigungsdatum kann aufgrund der Mindestlaufzeit nicht vor dem '.
                                             $minEndDate->format('d.m.Y').' liegen.',
                    ]);
                }
            }

            // Check the notice period
            if ($membership->membershipPlan->cancellation_period) {
                $cancellationPeriod = $membership->membershipPlan->cancellation_period;
                $cancellationUnit = $membership->membershipPlan->cancellation_period_unit ?? 'days';

                if ($cancellationUnit === 'months') {
                    $minCancellationDate = now()->addMonths($cancellationPeriod);
                } else {
                    $minCancellationDate = now()->addDays($cancellationPeriod);
                }

                if (Carbon::parse($validated['cancellation_date'])->lt($minCancellationDate)) {
                    return back()->withErrors([
                        'cancellation_date' => 'Die Kündigungsfrist beträgt '.
                                             $membership->membershipPlan->formatted_cancellation_period.
                                             '. Frühestmöglicher Kündigungstermin: '.
                                             $minCancellationDate->format('d.m.Y'),
                    ]);
                }
            }
        }

        DB::beginTransaction();
        try {
            // Convert the cancellation reason into a readable label
            $reasonText = [
                'move' => 'Umzug',
                'financial' => 'Finanzielle Gründe',
                'health' => 'Gesundheitliche Gründe',
                'dissatisfied' => 'Unzufriedenheit',
                'no_time' => 'Zeitmangel',
                'other' => 'Sonstiges',
            ][$validated['cancellation_reason']] ?? $validated['cancellation_reason'];

            // A free-text note on "Sonstiges" replaces the generic parenthesis,
            // so the stored reason names the actual cause instead of just the
            // cancellation type.
            $reasonNote = $validated['cancellation_reason'] === 'other'
                ? trim($validated['cancellation_reason_note'] ?? '')
                : '';
            $qualifier = $reasonNote !== '' ? $reasonNote : 'Außerordentliche Kündigung';

            // Immediate cancellation
            if ($request->input('immediate', false)) {
                $membership->update([
                    'status' => 'cancelled',
                    'cancellation_date' => now(),
                    'cancellation_reason' => $reasonText.' ('.$qualifier.')',
                    'end_date' => now(),
                ]);
            } else {
                // Regular cancellation, effective on the given date
                $membership->update([
                    'cancellation_date' => $validated['cancellation_date'],
                    'cancellation_reason' => $isExtraordinary || $reasonNote !== ''
                        ? $reasonText.' ('.$qualifier.')'
                        : $reasonText,
                ]);

                // The status only turns 'cancelled' on the cancellation date;
                // a cron job or task scheduler could take care of that.
            }

            // Append a note
            $membership->update([
                'notes' => ($membership->notes ? $membership->notes."\n" : '').
                          'Gekündigt am '.now()->format('d.m.Y').
                          ' zum '.Carbon::parse($validated['cancellation_date'])->format('d.m.Y').
                          ' - Grund: '.$reasonText,
            ]);

            DB::commit();

            // Send the confirmation only after the commit, so a delivery failure
            // cannot roll back a cancellation that is already stored. The
            // dispatcher handles synthetic/missing address checks, logging and
            // exception wrapping.
            if ($request->boolean('send_confirmation')) {
                $this->mailDispatcher->sendToMember(
                    $member,
                    new CancellationConfirmationMail(
                        $member,
                        $membership->fresh(),
                        $member->gym,
                    ),
                );
            }

            return back()->with('success', 'Die Mitgliedschaft wurde erfolgreich gekündigt.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'Die Mitgliedschaft konnte nicht gekündigt werden: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Revokes a cancellation.
     */
    public function revokeCancellation(Request $request, Member $member, Membership $membership)
    {
        $this->authorize('update', $membership);

        // Check that the membership belongs to this member
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Check that a cancellation exists
        if (! $membership->cancellation_date) {
            return back()->withErrors([
                'cancellation' => 'Diese Mitgliedschaft wurde nicht gekündigt.',
            ]);
        }

        // Check that the cancellation has not taken effect yet
        if ($membership->status === 'cancelled' &&
            Carbon::parse($membership->cancellation_date)->isPast()) {
            return back()->withErrors([
                'cancellation' => 'Die Kündigung ist bereits wirksam und kann nicht mehr zurückgenommen werden.',
            ]);
        }

        DB::beginTransaction();
        try {
            // Revoke the cancellation
            $previousStatus = $membership->pause_start_date &&
                             Carbon::parse($membership->pause_start_date)->isPast() &&
                             Carbon::parse($membership->pause_end_date)->isFuture()
                             ? 'paused' : 'active';

            $membership->update([
                'status' => $previousStatus,
                'cancellation_date' => null,
                'cancellation_reason' => null,
            ]);

            // Append a note
            $membership->update([
                'notes' => ($membership->notes ? $membership->notes."\n" : '').
                          'Kündigung zurückgenommen am '.now()->format('d.m.Y'),
            ]);

            DB::commit();

            return back()->with('success', 'Die Kündigung wurde erfolgreich zurückgenommen.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'Die Kündigung konnte nicht zurückgenommen werden: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Ends a free trial period immediately.
     */
    public function abort(Request $request, Member $member, Membership $membership)
    {
        $this->authorize('update', $membership);

        // Check that the membership belongs to this member
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Check that this really is a free trial period
        if (! $membership->is_free_trial) {
            return back()->withErrors([
                'error' => 'Nur Gratis-Testzeiträume können abgebrochen werden.',
            ]);
        }

        // Check that the membership is active
        if ($membership->status !== 'active') {
            return back()->withErrors([
                'status' => 'Nur aktive Gratis-Testzeiträume können abgebrochen werden.',
            ]);
        }

        DB::beginTransaction();
        try {
            // Set the end date to today and the status to expired
            $membership->update([
                'status' => 'expired',
                'end_date' => now()->format('Y-m-d'),
            ]);

            // Append a note
            $membership->update([
                'notes' => ($membership->notes ? $membership->notes."\n" : '').
                          'Gratis-Testzeitraum abgebrochen am '.now()->format('d.m.Y H:i'),
            ]);

            DB::commit();

            return back()->with('success', 'Der Gratis-Testzeitraum wurde erfolgreich abgebrochen.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'Der Gratis-Testzeitraum konnte nicht abgebrochen werden: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Forces a status change without any further checks.
     */
    public function forceStatus(Request $request, Member $member, Membership $membership)
    {
        $this->authorize('update', $membership);

        $validated = $request->validate([
            'status' => 'required|string|in:active,paused,cancelled,expired,pending,withdrawn',
        ]);

        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        $newStatus = $validated['status'];

        if ($membership->status === $newStatus) {
            return back()->withErrors([
                'status' => 'Die Mitgliedschaft befindet sich bereits im Status "'.$newStatus.'".',
            ]);
        }

        DB::beginTransaction();
        try {
            $previousStatus = $membership->status;

            $membership->update([
                'status' => $newStatus,
                'notes' => ($membership->notes ? $membership->notes."\n" : '').
                          "Status forciert: {$previousStatus} → {$newStatus} am ".now()->format('d.m.Y H:i').
                          ' (durch '.auth()->user()->name.')',
            ]);

            Log::info('Membership status force-changed', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'admin_user_id' => auth()->id(),
            ]);

            DB::commit();

            $statusLabels = [
                'active' => 'Aktiv',
                'paused' => 'Pausiert',
                'cancelled' => 'Gekündigt',
                'expired' => 'Abgelaufen',
                'pending' => 'Ausstehend',
                'withdrawn' => 'Widerrufen',
            ];

            return back()->with('success', 'Der Mitgliedschafts-Status wurde auf "'.($statusLabels[$newStatus] ?? $newStatus).'" geändert.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'error' => 'Der Status konnte nicht geändert werden: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Withdraws a membership under § 356a BGB.
     *
     * A manual withdrawal from the admin area triggers the confirmation
     * mail as well.
     */
    public function withdraw(
        Request $request,
        Member $member,
        Membership $membership,
        MembershipService $membershipService,
    ) {
        $this->authorize('update', $membership);

        // Check that the membership belongs to this member
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Validation
        $validated = $request->validate([
            'confirmation_email' => 'nullable|email|max:255',
            'force' => 'nullable|boolean',
        ]);

        // Skip the checks when forced
        if (! $request->boolean('force')) {
            $eligibility = $membershipService->checkWithdrawalEligibility($membership);

            if (! $eligibility['eligible']) {
                return back()->withErrors([
                    'error' => $eligibility['reason'],
                ]);
            }
        }

        // Address from the request, falling back to the member profile
        $confirmationEmail = $validated['confirmation_email'] ?? $member->email;

        try {
            $refundAmount = $membershipService->withdraw(
                $membership,
                $confirmationEmail,
                source: 'admin_withdrawal',
                note: 'Widerrufen am '.now()->format('d.m.Y H:i').' (manuell durch Admin)',
            );
        } catch (\Exception $e) {
            Log::error('Manual contract withdrawal failed', [
                'member_id' => $member->id,
                'membership_id' => $membership->id,
                'admin_user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Der Widerruf konnte nicht durchgeführt werden: '.$e->getMessage(),
            ]);
        }

        $successMessage = 'Die Mitgliedschaft wurde erfolgreich widerrufen.';
        if ($refundAmount > 0) {
            $successMessage .= ' Erstattung von '.number_format($refundAmount, 2, ',', '.').' € wurde initiiert.';
        }

        return back()->with('success', $successMessage);
    }

    /**
     * Toggle the completion state of a booked add-on (e.g. trainer induction).
     * Records when it was completed and by which staff user, or clears both when
     * resetting.
     */
    public function toggleAddonCompletion(Request $request, Member $member, Membership $membership, Addon $addon)
    {
        $this->authorize('update', $membership);

        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Ensure the add-on is actually booked for this membership.
        $pivot = $membership->addons()->find($addon->id)?->pivot;

        if (! $pivot) {
            return back()->withErrors([
                'error' => 'Dieses Add-on ist für diese Mitgliedschaft nicht gebucht.',
            ]);
        }

        $isCompleted = $pivot->completed_at !== null;

        $membership->addons()->updateExistingPivot($addon->id, [
            'completed_at' => $isCompleted ? null : now(),
            'completed_by' => $isCompleted ? null : $request->user()->id,
        ]);

        return back()->with(
            'success',
            $isCompleted ? 'Add-on wurde als offen markiert.' : 'Add-on wurde als erledigt markiert.'
        );
    }

    /**
     * Book an add-on for an existing membership.
     *
     * Only add-ons assigned to the membership's plan can be booked, mirroring
     * what the booking widget offers. No payment is created here: the charge is
     * handled manually or by the billing run, so booking never moves money on
     * its own.
     */
    public function storeMembershipAddon(Request $request, Member $member, Membership $membership)
    {
        $this->authorize('update', $membership);

        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        $validated = $request->validate([
            'addon_id' => 'required|integer|exists:addons,id',
        ], [
            'addon_id.required' => 'Bitte wähle ein Add-on aus.',
        ]);

        // The add-on must be active and assigned to this membership's plan.
        $addon = $membership->membershipPlan
            ?->addons()
            ->where('addons.id', $validated['addon_id'])
            ->where('is_active', true)
            ->first();

        if (! $addon) {
            return back()->withErrors([
                'error' => 'Dieses Add-on ist für den Vertrag dieser Mitgliedschaft nicht verfügbar.',
            ]);
        }

        if ($membership->addons()->where('addons.id', $addon->id)->exists()) {
            return back()->withErrors([
                'error' => 'Dieses Add-on ist für diese Mitgliedschaft bereits gebucht.',
            ]);
        }

        $mode = $addon->pivot->mode;

        $membership->addons()->attach($addon->id, [
            'mode' => $mode,
            // Included add-ons are part of the plan and therefore free.
            'price' => $mode === 'included' ? 0 : $addon->price,
        ]);

        return back()->with('success', 'Add-on wurde gebucht.');
    }

    /**
     * Cancel a booked recurring add-on, or revoke a pending cancellation.
     *
     * Recurring add-ons are cancellable to the end of the current billing
     * period, so the service stays usable until then and only the following
     * period is no longer billed. Billing periods are anchored to the
     * membership start date and only match calendar months when the contract
     * itself started on the 1st.
     */
    public function toggleAddonCancellation(Request $request, Member $member, Membership $membership, Addon $addon)
    {
        $this->authorize('update', $membership);

        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Ensure the add-on is actually booked for this membership.
        $pivot = $membership->addons()->find($addon->id)?->pivot;

        if (! $pivot) {
            return back()->withErrors([
                'error' => 'Dieses Add-on ist für diese Mitgliedschaft nicht gebucht.',
            ]);
        }

        // Only recurring add-ons have an ongoing term that can be cancelled.
        if (! $addon->isRecurring()) {
            return back()->withErrors([
                'error' => 'Nur wiederkehrende Add-ons können gekündigt werden.',
            ]);
        }

        $isCancelled = $pivot->cancelled_at !== null;
        $effectiveAt = $membership->billingPeriodEnd();

        $membership->addons()->updateExistingPivot($addon->id, [
            'cancelled_at' => $isCancelled ? null : now(),
            'cancellation_effective_at' => $isCancelled ? null : $effectiveAt?->toDateString(),
            'cancelled_by' => $isCancelled ? null : $request->user()->id,
        ]);

        return back()->with(
            'success',
            $isCancelled
                ? 'Die Kündigung des Add-ons wurde zurückgenommen.'
                : 'Add-on wurde zum '.$effectiveAt?->format('d.m.Y').' gekündigt.'
        );
    }
}
