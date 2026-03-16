<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberBlocklist;
use App\Models\FraudCheck;
use App\Services\Fraud\BlocklistService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BlocklistController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected BlocklistService $blocklist) {}

    /**
     * Sperrliste anzeigen.
     */
    public function index(Request $request): \Inertia\Response
    {
        $user = Auth::user();
        $gym  = $user->currentGym;

        $this->authorize('manage', $gym);

        $entries = MemberBlocklist::with(['member:id,first_name,last_name,email,member_number', 'blockedByUser:id,first_name,last_name'])
            ->where('gym_id', $gym->id)
            ->orderByDesc('blocked_at')
            ->paginate(25);

        $fraudChecks = FraudCheck::where('gym_id', $gym->id)
            ->where('action', '!=', 'allowed')
            ->orderByDesc('checked_at')
            ->limit(50)
            ->get();

        return Inertia::render('Admin/Blocklist/Index', [
            'entries'     => $entries,
            'fraudChecks' => $fraudChecks,
        ]);
    }

    /**
     * Mitglied direkt über das Admin-Panel sperren.
     */
    public function blockMember(Request $request, Member $member): RedirectResponse
    {
        $user = Auth::user();
        $gym  = $user->currentGym;

        $this->authorize('manage', $gym);

        $request->validate([
            'reason' => ['required', 'in:payment_failed,chargeback,fraud,manual'],
            'notes'  => ['required', 'string', 'min:10', 'max:500'],
        ]);

        abort_if($member->gym_id !== $gym->id, 403);

        $this->blocklist->addMember(
            gymId:           $gym->id,
            member:          $member,
            reason:          $request->reason,
            notes:           $request->notes,
            blockedByUserId: $user->id,
        );

        // Zugang sofort sperren
        $member->update(['status' => 'blocked']);

        return back()->with('success', "Mitglied {$member->full_name} wurde gesperrt.");
    }

    /**
     * Sperrliste manuell befüllen (ohne verknüpftes Mitglied).
     */
    public function storeManual(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $gym  = $user->currentGym;

        $this->authorize('manage', $gym);

        $data = $request->validate([
            'first_name'    => ['nullable', 'string'],
            'last_name'     => ['required', 'string'],
            'birth_date'    => ['nullable', 'date'],
            'iban'          => ['nullable', 'string'],
            'phone'         => ['nullable', 'string'],
            'address'       => ['nullable', 'string'],
            'postal_code'   => ['nullable', 'string'],
            'city'          => ['nullable', 'string'],
            'reason'        => ['required', 'in:payment_failed,chargeback,fraud,manual'],
            'notes'         => ['required', 'string', 'min:10'],
            'blocked_until' => ['nullable', 'date', 'after:now'],
            'member_id'     => ['nullable', 'integer', 'exists:members,id'],
        ]);

        $this->blocklist->addManual($gym->id, $data, $user->id, $data['reason'], $data['notes']);

        return back()->with('success', 'Eintrag zur Sperrliste hinzugefügt.');
    }

    /**
     * Sperre aufheben (Begründung Pflicht).
     */
    public function unblock(Request $request, MemberBlocklist $entry): RedirectResponse
    {
        $user = Auth::user();
        $gym  = $user->currentGym;

        $this->authorize('manage', $gym);

        abort_if($entry->gym_id !== $gym->id, 403);

        $request->validate([
            'unblock_reason' => ['required', 'string', 'min:10'],
        ]);

        $this->blocklist->unblock($entry, $user->id, $request->unblock_reason);

        return back()->with('success', 'Sperre wurde aufgehoben.');
    }
}
