<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class MembershipPlanController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the membership plans.
     */
    public function index(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $membershipPlans = MembershipPlan::where('gym_id', $user->current_gym_id)
            ->withCount(['memberships as member_count' => function ($query) {
                $query->active();
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('MembershipPlans/Index', [
            'membershipPlans' => $membershipPlans,
            'flash' => session('flash')
        ]);
    }

    /**
     * Show the form for creating a new membership plan.
     */
    public function create(): Response
    {
        return Inertia::render('MembershipPlans/Create');
    }

    /**
     * Store a newly created membership plan in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MembershipPlan::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:9999.99',
            'setup_fee' => 'nullable|numeric|min:0|max:999.99',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly',
            'is_active' => 'boolean',
            'commitment_months' => 'nullable|integer|min:0|max:36',
            'cancellation_period' => 'required|integer|min:0',
            'cancellation_period_unit' => 'required|in:days,months',
        ]);

        // Additional validation based on unit
        if ($request->cancellation_period_unit === 'months' && $request->cancellation_period > 24) {
            return back()->withErrors(['cancellation_period' => 'Die Kündigungsfrist darf maximal 24 Monate betragen.'])->withInput();
        } elseif ($request->cancellation_period_unit === 'days' && $request->cancellation_period > 365) {
            return back()->withErrors(['cancellation_period' => 'Die Kündigungsfrist darf maximal 365 Tage betragen.'])->withInput();
        }

        /** @var User $user */
        $user = Auth::user();

        $validated['gym_id'] = $user->current_gym_id;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['setup_fee'] = $request->setup_fee ?? 0;
        $validated['commitment_months'] = $request->commitment_months ?? 0;

        MembershipPlan::create($validated);

        return Redirect::route('contracts.index')->with('flash', [
            'type' => 'success',
            'message' => 'Mitgliedschaftsplan wurde erfolgreich erstellt.'
        ]);
    }

    /**
     * Display the specified membership plan.
     */
    public function show(MembershipPlan $membershipPlan): Response
    {
        $this->authorize('view', $membershipPlan);

        $activeMemberships = $membershipPlan->memberships()
            ->where('status', 'active')
            ->with(['member'])
            ->get();

        return Inertia::render('MembershipPlans/Show', [
            'membershipPlan' => $membershipPlan,
            'activeMemberships' => $activeMemberships,
            'activeMembersCount' => $activeMemberships->count()
        ]);
    }

    /**
     * Show the form for editing the specified membership plan.
     */
    public function edit(MembershipPlan $membershipPlan): Response
    {
        $this->authorize('update', $membershipPlan);

        $activeMembersCount = $membershipPlan->memberships()
            ->where('status', 'active')
            ->count();

        $activeMemberships = [];
        if ($activeMembersCount > 0) {
            $activeMemberships = $membershipPlan->memberships()
                ->where('status', 'active')
                ->with(['member'])
                ->limit(10)
                ->get();
        }

        return Inertia::render('MembershipPlans/Edit', [
            'membershipPlan' => $membershipPlan,
            'activeMembersCount' => $activeMembersCount,
            'activeMemberships' => $activeMemberships
        ]);
    }

    /**
     * Update the specified membership plan in storage.
     */
    public function update(Request $request, MembershipPlan $membershipPlan): RedirectResponse
    {
        $this->authorize('update', $membershipPlan);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:9999.99',
            'setup_fee' => 'numeric|min:0|max:999.99',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly',
            'is_active' => 'boolean',
            'commitment_months' => 'nullable|integer|min:0|max:36',
            'cancellation_period' => 'required|integer|min:0',
            'cancellation_period_unit' => 'required|in:days,months',
        ]);

        // Additional validation based on unit
        if ($request->cancellation_period_unit === 'months' && $request->cancellation_period > 24) {
            return back()->withErrors(['cancellation_period' => 'Die Kündigungsfrist darf maximal 24 Monate betragen.'])->withInput();
        } elseif ($request->cancellation_period_unit === 'days' && $request->cancellation_period > 365) {
            return back()->withErrors(['cancellation_period' => 'Die Kündigungsfrist darf maximal 365 Tage betragen.'])->withInput();
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['commitment_months'] = $request->commitment_months ?? 0;

        $membershipPlan->update($validated);

        return Redirect::route('contracts.index')->with('flash', [
            'type' => 'success',
            'message' => 'Mitgliedschaftsplan wurde erfolgreich aktualisiert.'
        ]);
    }

    /**
     * Remove the specified membership plan from storage.
     */
    public function destroy(MembershipPlan $membershipPlan): RedirectResponse
    {
        $this->authorize('delete', $membershipPlan);

        // Check if there are active members using this plan
        $activeMembersCount = $membershipPlan->memberships()
            ->where('status', 'active')
            ->count();

        if ($activeMembersCount > 0) {
            $activeMembers = $membershipPlan->memberships()
                ->where('status', 'active')
                ->with(['member'])
                ->limit(5)
                ->get();

            $memberNames = $activeMembers->pluck('member.first_name')->join(', ');
            $additionalCount = max(0, $activeMembersCount - 5);
            $additionalText = $additionalCount > 0 ? " und {$additionalCount} weitere" : '';

            return Redirect::route('contracts.index')->with('flash', [
                'type' => 'error',
                'message' => "Dieser Mitgliedschaftsplan kann nicht gelöscht werden, da noch {$activeMembersCount} aktive Mitglieder diesen nutzen: {$memberNames}{$additionalText}."
            ]);
        }

        $membershipPlan->delete();

        return Redirect::route('contracts.index')->with('flash', [
            'type' => 'success',
            'message' => 'Mitgliedschaftsplan wurde erfolgreich gelöscht.'
        ]);
    }

    /**
     * Check if membership plan can be deleted
     */
    public function checkDeletion(MembershipPlan $membershipPlan)
    {
        $this->authorize('delete', $membershipPlan);

        $activeMembersCount = $membershipPlan->memberships()
            ->where('status', 'active')
            ->count();

        if ($activeMembersCount > 0) {
            $activeMembers = $membershipPlan->memberships()
                ->where('status', 'active')
                ->with(['member'])
                ->limit(10)
                ->get();

            return response()->json([
                'canDelete' => false,
                'activeMembersCount' => $activeMembersCount,
                'activeMembers' => $activeMembers->map(function ($membership) {
                    return [
                        'id' => $membership->id,
                        'name' => $membership->member->first_name . ' ' . $membership->member->last_name,
                        'email' => $membership->member->email
                    ];
                })
            ]);
        }

        return response()->json([
            'canDelete' => true,
            'activeMembersCount' => 0,
            'activeMembers' => []
        ]);
    }
}
