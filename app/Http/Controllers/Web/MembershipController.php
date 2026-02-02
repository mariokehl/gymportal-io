<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Membership;
use App\Services\MemberService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MembershipController extends Controller
{
    public function __construct(
        private MemberService $memberService
    ) {}

    /**
     * Erstellt einen kostenlosen Zeitraum (z.B. Probetraining)
     */
    public function storeFreePeriod(Request $request, Member $member)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'linked_membership_id' => 'nullable|exists:memberships,id',
        ], [
            'start_date.required' => 'Das Startdatum ist erforderlich.',
            'end_date.required' => 'Das Enddatum ist erforderlich.',
            'end_date.after_or_equal' => 'Das Enddatum muss nach dem Startdatum liegen.',
        ]);

        // Gym des aktuellen Benutzers
        $gym = auth()->user()->gym;

        DB::beginTransaction();
        try {
            // Verknüpfte Mitgliedschaft prüfen (falls angegeben)
            $linkedMembership = null;
            if ($validated['linked_membership_id']) {
                $linkedMembership = Membership::where('id', $validated['linked_membership_id'])
                    ->where('member_id', $member->id)
                    ->first();

                if (!$linkedMembership) {
                    return back()->withErrors([
                        'linked_membership_id' => 'Die ausgewählte Mitgliedschaft gehört nicht zu diesem Mitglied.'
                    ]);
                }
            }

            // Gratis-Mitgliedschaft erstellen
            $freeMembership = $this->memberService->createFreePeriodMembership(
                $member,
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date']),
                $linkedMembership
            );

            // Verknüpfung in der anderen Richtung speichern
            if ($linkedMembership) {
                $linkedMembership->update([
                    'linked_free_membership_id' => $freeMembership->id
                ]);
            }

            DB::commit();

            return back()->with('success', 'Der kostenlose Zeitraum wurde erfolgreich erstellt.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Der kostenlose Zeitraum konnte nicht erstellt werden: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Aktiviert eine pending Mitgliedschaft
     */
    public function activate(Request $request, Member $member, Membership $membership)
    {
        // Überprüfen ob die Mitgliedschaft zum Mitglied gehört
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Überprüfen ob die Mitgliedschaft aktiviert werden kann
        if ($membership->status !== 'pending') {
            return back()->withErrors([
                'status' => 'Nur ausstehende Mitgliedschaften können aktiviert werden.'
            ]);
        }

        DB::beginTransaction();
        try {
            // Mitgliedschaft aktivieren
            $membership->update([
                'status' => 'active',
            ]);

            // Mitglied auch aktivieren, falls noch pending
            if ($member->status === 'pending') {
                $member->update(['status' => 'active']);
            }

            // Notiz hinzufügen
            $membership->update([
                'notes' => ($membership->notes ? $membership->notes . "\n" : '') .
                          "Manuell aktiviert am " . now()->format('d.m.Y H:i')
            ]);

            // Optional: E-Mail an Mitglied senden
            // Mail::to($member->email)->send(new MembershipActivated($membership));

            DB::commit();

            return back()->with('success', 'Die Mitgliedschaft wurde erfolgreich aktiviert.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Die Mitgliedschaft konnte nicht aktiviert werden: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Pausiert eine Mitgliedschaft
     */
    public function pause(Request $request, Member $member, Membership $membership)
    {
        // Validierung
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

        // Überprüfen ob die Mitgliedschaft zum Mitglied gehört
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Überprüfen ob die Mitgliedschaft pausiert werden kann
        if (!in_array($membership->status, ['active'])) {
            return back()->withErrors([
                'status' => 'Nur aktive Mitgliedschaften können pausiert werden.'
            ]);
        }

        DB::beginTransaction();
        try {
            // Mitgliedschaft pausieren
            $membership->update([
                'status' => 'paused',
                'pause_start_date' => $validated['pause_start_date'],
                'pause_end_date' => $validated['pause_end_date'],
            ]);

            // Optional: Pausierungsgrund in widget_data oder notes speichern
            if ($validated['reason']) {
                $membership->update([
                    'notes' => ($membership->notes ? $membership->notes . "\n" : '') .
                              "Pausiert am " . now()->format('d.m.Y') . ": " . $validated['reason']
                ]);
            }

            // Verlängere das End-Datum der Mitgliedschaft um die Pausierungsdauer
            if ($membership->end_date) {
                $pauseDays = \Carbon\Carbon::parse($validated['pause_start_date'])
                    ->diffInDays(\Carbon\Carbon::parse($validated['pause_end_date']));

                $newEndDate = \Carbon\Carbon::parse($membership->end_date)->addDays($pauseDays);
                $membership->update(['end_date' => $newEndDate]);
            }

            DB::commit();

            return back()->with('success', 'Die Mitgliedschaft wurde erfolgreich pausiert.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Die Mitgliedschaft konnte nicht pausiert werden: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Nimmt eine pausierte Mitgliedschaft wieder auf
     */
    public function resume(Request $request, Member $member, Membership $membership)
    {
        // Überprüfen ob die Mitgliedschaft zum Mitglied gehört
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Überprüfen ob die Mitgliedschaft wieder aufgenommen werden kann
        if ($membership->status !== 'paused') {
            return back()->withErrors([
                'status' => 'Nur pausierte Mitgliedschaften können wieder aufgenommen werden.'
            ]);
        }

        DB::beginTransaction();
        try {
            // Berechne die tatsächliche Pausierungsdauer, falls früher wieder aufgenommen
            $actualPauseEnd = now()->format('Y-m-d');
            $originalPauseEnd = $membership->pause_end_date;

            if ($actualPauseEnd < $originalPauseEnd) {
                // Anpassung des End-Datums, wenn früher wieder aufgenommen
                $unusedPauseDays = \Carbon\Carbon::parse($actualPauseEnd)
                    ->diffInDays(\Carbon\Carbon::parse($originalPauseEnd));

                if ($membership->end_date) {
                    $adjustedEndDate = \Carbon\Carbon::parse($membership->end_date)
                        ->subDays($unusedPauseDays);
                    $membership->end_date = $adjustedEndDate;
                }
            }

            // Mitgliedschaft wieder aktivieren
            $membership->update([
                'status' => 'active',
                'pause_end_date' => $actualPauseEnd,
            ]);

            // Notiz hinzufügen
            $membership->update([
                'notes' => ($membership->notes ? $membership->notes . "\n" : '') .
                          "Wieder aufgenommen am " . now()->format('d.m.Y')
            ]);

            DB::commit();

            return back()->with('success', 'Die Mitgliedschaft wurde erfolgreich wieder aufgenommen.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Die Mitgliedschaft konnte nicht wieder aufgenommen werden: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Kündigt eine Mitgliedschaft
     */
    public function cancel(Request $request, Member $member, Membership $membership)
    {
        // Validierung
        $validated = $request->validate([
            'cancellation_date' => 'required|date|after_or_equal:today',
            'cancellation_reason' => 'required|string|in:move,financial,health,dissatisfied,no_time,other',
            'immediate' => 'boolean',
        ], [
            'cancellation_date.required' => 'Das Kündigungsdatum ist erforderlich.',
            'cancellation_date.after_or_equal' => 'Das Kündigungsdatum muss heute oder in der Zukunft liegen.',
            'cancellation_reason.required' => 'Der Kündigungsgrund ist erforderlich.',
        ]);

        // Überprüfen ob die Mitgliedschaft zum Mitglied gehört
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Überprüfen ob die Mitgliedschaft gekündigt werden kann
        if (!in_array($membership->status, ['active', 'paused'])) {
            return back()->withErrors([
                'status' => 'Diese Mitgliedschaft kann nicht gekündigt werden.'
            ]);
        }

        // Überprüfen ob bereits eine Kündigung vorliegt
        if ($membership->cancellation_date) {
            return back()->withErrors([
                'cancellation' => 'Diese Mitgliedschaft wurde bereits gekündigt.'
            ]);
        }

        // Mindestlaufzeit prüfen (außer bei sofortiger Kündigung aus wichtigem Grund)
        if (!$request->input('immediate', false)) {
            // Mindestlaufzeit prüfen
            if ($membership->membershipPlan->commitment_months) {
                $minEndDate = \Carbon\Carbon::parse($membership->start_date)
                    ->addMonths($membership->membershipPlan->commitment_months);

                if (\Carbon\Carbon::parse($validated['cancellation_date'])->lt($minEndDate)) {
                    return back()->withErrors([
                        'cancellation_date' => 'Das Kündigungsdatum kann aufgrund der Mindestlaufzeit nicht vor dem ' .
                                             $minEndDate->format('d.m.Y') . ' liegen.'
                    ]);
                }
            }

            // Kündigungsfrist prüfen
            if ($membership->membershipPlan->cancellation_period_days) {
                $minCancellationDate = now()->addDays($membership->membershipPlan->cancellation_period_days);

                if (\Carbon\Carbon::parse($validated['cancellation_date'])->lt($minCancellationDate)) {
                    return back()->withErrors([
                        'cancellation_date' => 'Die Kündigungsfrist beträgt ' .
                                             $membership->membershipPlan->cancellation_period_days .
                                             ' Tage. Frühestmöglicher Kündigungstermin: ' .
                                             $minCancellationDate->format('d.m.Y')
                    ]);
                }
            }
        }

        DB::beginTransaction();
        try {
            // Kündigungsgrund in lesbares Format konvertieren
            $reasonText = [
                'move' => 'Umzug',
                'financial' => 'Finanzielle Gründe',
                'health' => 'Gesundheitliche Gründe',
                'dissatisfied' => 'Unzufriedenheit',
                'no_time' => 'Zeitmangel',
                'other' => 'Sonstiges'
            ][$validated['cancellation_reason']] ?? $validated['cancellation_reason'];

            // Bei sofortiger Kündigung
            if ($request->input('immediate', false)) {
                $membership->update([
                    'status' => 'cancelled',
                    'cancellation_date' => now(),
                    'cancellation_reason' => $reasonText . ' (Außerordentliche Kündigung)',
                    'end_date' => now(),
                ]);
            } else {
                // Reguläre Kündigung zum angegebenen Datum
                $membership->update([
                    'cancellation_date' => $validated['cancellation_date'],
                    'cancellation_reason' => $reasonText,
                ]);

                // Status wird erst am Kündigungsdatum auf 'cancelled' gesetzt
                // Dies könnte durch einen Cronjob oder Task Scheduler erfolgen
            }

            // Notiz hinzufügen
            $membership->update([
                'notes' => ($membership->notes ? $membership->notes . "\n" : '') .
                          "Gekündigt am " . now()->format('d.m.Y') .
                          " zum " . \Carbon\Carbon::parse($validated['cancellation_date'])->format('d.m.Y') .
                          " - Grund: " . $reasonText
            ]);

            // Optional: E-Mail an Mitglied senden
            // Mail::to($member->email)->send(new MembershipCancellationConfirmation($membership));

            DB::commit();

            return back()->with('success', 'Die Mitgliedschaft wurde erfolgreich gekündigt.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Die Mitgliedschaft konnte nicht gekündigt werden: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Nimmt eine Kündigung zurück
     */
    public function revokeCancellation(Request $request, Member $member, Membership $membership)
    {
        // Überprüfen ob die Mitgliedschaft zum Mitglied gehört
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Überprüfen ob eine Kündigung vorliegt
        if (!$membership->cancellation_date) {
            return back()->withErrors([
                'cancellation' => 'Diese Mitgliedschaft wurde nicht gekündigt.'
            ]);
        }

        // Überprüfen ob die Kündigung noch nicht wirksam ist
        if ($membership->status === 'cancelled' &&
            \Carbon\Carbon::parse($membership->cancellation_date)->isPast()) {
            return back()->withErrors([
                'cancellation' => 'Die Kündigung ist bereits wirksam und kann nicht mehr zurückgenommen werden.'
            ]);
        }

        DB::beginTransaction();
        try {
            // Kündigung zurücknehmen
            $previousStatus = $membership->pause_start_date &&
                             \Carbon\Carbon::parse($membership->pause_start_date)->isPast() &&
                             \Carbon\Carbon::parse($membership->pause_end_date)->isFuture()
                             ? 'paused' : 'active';

            $membership->update([
                'status' => $previousStatus,
                'cancellation_date' => null,
                'cancellation_reason' => null,
            ]);

            // Notiz hinzufügen
            $membership->update([
                'notes' => ($membership->notes ? $membership->notes . "\n" : '') .
                          "Kündigung zurückgenommen am " . now()->format('d.m.Y')
            ]);

            DB::commit();

            return back()->with('success', 'Die Kündigung wurde erfolgreich zurückgenommen.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Die Kündigung konnte nicht zurückgenommen werden: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Bricht einen Gratis-Testzeitraum sofort ab
     */
    public function abort(Request $request, Member $member, Membership $membership)
    {
        // Überprüfen ob die Mitgliedschaft zum Mitglied gehört
        if ($membership->member_id !== $member->id) {
            abort(403, 'Diese Mitgliedschaft gehört nicht zu diesem Mitglied.');
        }

        // Überprüfen ob es sich um einen Gratis-Testzeitraum handelt
        if (!$membership->is_free_trial) {
            return back()->withErrors([
                'error' => 'Nur Gratis-Testzeiträume können abgebrochen werden.'
            ]);
        }

        // Überprüfen ob die Mitgliedschaft aktiv ist
        if ($membership->status !== 'active') {
            return back()->withErrors([
                'status' => 'Nur aktive Gratis-Testzeiträume können abgebrochen werden.'
            ]);
        }

        DB::beginTransaction();
        try {
            // Enddatum auf heute setzen und Status auf expired
            $membership->update([
                'status' => 'expired',
                'end_date' => now()->format('Y-m-d'),
            ]);

            // Notiz hinzufügen
            $membership->update([
                'notes' => ($membership->notes ? $membership->notes . "\n" : '') .
                          "Gratis-Testzeitraum abgebrochen am " . now()->format('d.m.Y H:i')
            ]);

            DB::commit();

            return back()->with('success', 'Der Gratis-Testzeitraum wurde erfolgreich abgebrochen.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors([
                'error' => 'Der Gratis-Testzeitraum konnte nicht abgebrochen werden: ' . $e->getMessage()
            ]);
        }
    }
}
