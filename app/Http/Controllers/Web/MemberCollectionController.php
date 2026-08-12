<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CollectionCase;
use App\Models\Member;
use App\Services\CollectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class MemberCollectionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected CollectionService $collectionService) {}

    /**
     * Collection data of a member for the Inkasso tab.
     */
    public function show(Member $member): JsonResponse
    {
        $this->authorize('view', $member);

        $cases = CollectionCase::where('member_id', $member->id)
            ->with(['claims', 'payments', 'run'])
            ->orderByDesc('handed_over_at')
            ->get();

        return response()->json([
            'cases' => $cases,
            'dunning_notices' => $member->dunningNotices()->get(),
            'current_level' => $member->current_dunning_level,
            'settings' => $member->gym->getInkassoSettingsForDisplay(),
        ]);
    }

    /**
     * Hand a single member over to the collection partner.
     */
    public function handover(Request $request, Member $member): RedirectResponse
    {
        $this->authorize('create', CollectionCase::class);
        $this->assertSameGym($member);

        $validated = $request->validate([
            'dunning_fee' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        try {
            $case = $this->collectionService->handOverMember(
                $member,
                $member->gym,
                null,
                isset($validated['dunning_fee']) ? (float) $validated['dunning_fee'] : null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['inkasso' => $e->getMessage()]);
        }

        $this->collectionService->transmitCase($case, $member->gym);

        return back()->with(
            'success',
            $member->first_name.' '.$member->last_name.' wurde zum Inkasso übergeben · Mahnstufe 4 · Zugangssperre gesetzt.'
        );
    }

    /**
     * Book a payment reported by the partner.
     */
    public function bookPayment(Request $request, CollectionCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'booked_at' => ['required', 'date'],
            'allocation_mode' => ['required', 'in:auto,manual'],
            'allocation' => ['nullable', 'array'],
            'allocation.*' => ['numeric', 'min:0'],
            'close_case' => ['nullable', 'boolean'],
        ]);

        try {
            $this->collectionService->bookPayment(
                $case,
                (float) $validated['amount'],
                Carbon::parse($validated['booked_at']),
                $validated['allocation_mode'],
                $validated['allocation'] ?? null,
                (bool) ($validated['close_case'] ?? false),
                Auth::id(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Zahlung über '.number_format((float) $validated['amount'], 2, ',', '.').' € verbucht.');
    }

    /**
     * Close a case because the partner finished working on it.
     */
    public function close(Request $request, CollectionCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->collectionService->closeCase($case, $validated['comment'] ?? null);

        return back()->with('success', 'Inkassofall abgeschlossen.');
    }

    /**
     * Cancel a case and release the claims.
     */
    public function cancel(Request $request, CollectionCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->collectionService->cancelCase($case, $validated['comment'] ?? null);

        return back()->with('success', 'Inkassofall storniert – Forderungen freigegeben.');
    }

    /**
     * Update the partner's file reference.
     */
    public function updateReference(Request $request, CollectionCase $case): RedirectResponse
    {
        $this->authorize('update', $case);

        $validated = $request->validate([
            'partner_reference' => ['required', 'string', 'max:36'],
        ]);

        $case->update(['partner_reference' => $validated['partner_reference']]);

        return back()->with('success', 'Aktenzeichen aktualisiert.');
    }

    /**
     * Guard against acting on members of another organisation.
     */
    protected function assertSameGym(Member $member): void
    {
        abort_unless(Auth::user()->current_gym_id === $member->gym_id, 403);
    }
}
