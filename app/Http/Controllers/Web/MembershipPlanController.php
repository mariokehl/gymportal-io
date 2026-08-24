<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Services\CrossLocationAccessService;
use App\Services\MembershipPlanAddonService;
use App\Services\MembershipPlanDiscountService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MembershipPlanController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CrossLocationAccessService $crossLocationService,
        private readonly MembershipPlanDiscountService $discountService,
        private readonly MembershipPlanAddonService $addonService
    ) {}

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
            'flash' => session('flash'),
        ]);
    }

    /**
     * Show the form for creating a new membership plan.
     */
    public function create(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('MembershipPlans/Create', [
            'addons' => $this->addonService->optionsForGym($user->current_gym_id),
            'addonModes' => (object) [],
        ]);
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
            'original_price' => 'nullable|numeric|min:0|max:9999.99|gt:price',
            'setup_fee' => 'nullable|numeric|min:0|max:999.99',
            'billing_cycle' => 'required|in:monthly,quarterly,biannual,yearly',
            'is_active' => 'boolean',
            'commitment_months' => 'nullable|integer|min:0|max:36',
            'cancellation_period' => 'required|integer|min:0',
            'cancellation_period_unit' => 'required|in:days,months',
            'auto_renew_type' => 'nullable|in:indefinite,monthly',
            'start_date_mode' => 'nullable|in:next_possible,fixed',
            'fixed_start_date' => 'nullable|required_if:start_date_mode,fixed|date',
            ...MembershipPlanDiscountService::rules(),
            ...MembershipPlanAddonService::rules(),
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
        $validated['auto_renew_type'] = $request->auto_renew_type ?? 'indefinite';
        $validated['start_date_mode'] = $request->start_date_mode ?? 'next_possible';
        $validated['fixed_start_date'] = $validated['start_date_mode'] === 'fixed'
            ? ($validated['fixed_start_date'] ?? null)
            : null;

        $discountPhases = $validated['discount_phases'] ?? [];
        $addonModes = $validated['addon_modes'] ?? [];
        unset($validated['discount_phases'], $validated['addon_modes']);
        // Discount phases run in months, so a non-monthly cycle turns them off
        // and MembershipPlanDiscountService::sync() then drops the phases.
        $validated['discounts_enabled'] = $request->boolean('discounts_enabled')
            && MembershipPlanDiscountService::supportsBillingCycle($validated['billing_cycle']);

        $membershipPlan = MembershipPlan::create($validated);

        $this->discountService->sync($membershipPlan, $discountPhases);
        $this->addonService->sync($membershipPlan, $addonModes);

        return Redirect::route('contracts.index')->with('flash', [
            'type' => 'success',
            'message' => 'Mitgliedschaftsplan wurde erfolgreich erstellt.',
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
            'activeMembersCount' => $activeMemberships->count(),
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

        $gym = Auth::user()->currentGym;
        $scope = $membershipPlan->location_scope ?? MembershipPlan::SCOPE_OWN;
        $allowedGymIds = $membershipPlan->allowedGyms()->pluck('gyms.id')->all();
        $locations = $this->crossLocationService->organizationLocations($gym);

        return Inertia::render('MembershipPlans/Edit', [
            'membershipPlan' => $membershipPlan->load('discountPhases'),
            'addons' => $this->addonService->optionsForGym($membershipPlan->gym_id),
            'addonModes' => (object) $this->addonService->modesFor($membershipPlan),
            'activeMembersCount' => $activeMembersCount,
            'activeMemberships' => $activeMemberships,
            'locationScope' => [
                'scope' => $scope,
                'allowed_gym_ids' => $allowedGymIds,
                'locations' => $locations,
                'has_siblings' => count($locations) > 1,
                // While this location only admits its own members, no contract
                // setting can produce a cross-location check-in — the tab says
                // so instead of offering a choice without effect.
                'location_rule' => $gym->checkinRule(),
                'gym_name' => $gym->name,
                'effect' => $this->crossLocationService->contractEffect($gym, $scope, $allowedGymIds),
            ],
        ]);
    }

    /**
     * Update the locations this plan is valid at.
     */
    public function updateLocations(Request $request, MembershipPlan $membershipPlan): JsonResponse
    {
        $this->authorize('update', $membershipPlan);

        $validated = $request->validate([
            'location_scope' => ['required', Rule::in(MembershipPlan::LOCATION_SCOPES)],
            'allowed_gym_ids' => ['array'],
            'allowed_gym_ids.*' => ['integer'],
        ], [], [
            'location_scope' => 'Standortgeltung',
            'allowed_gym_ids' => 'erlaubte Standorte',
        ]);

        $gym = Auth::user()->currentGym;

        // Never trust the submitted ids: only locations of the same organisation
        // may be selected, and the plan's own location is always implied.
        $organizationIds = $gym->organizationGyms()
            ->where('gyms.id', '!=', $membershipPlan->gym_id)
            ->pluck('gyms.id')
            ->all();

        $allowed = array_values(array_intersect(
            $validated['allowed_gym_ids'] ?? [],
            $organizationIds
        ));

        if ($validated['location_scope'] === MembershipPlan::SCOPE_SELECTED && $allowed === []) {
            return response()->json([
                'message' => 'Wählen Sie mindestens einen zusätzlichen Standort aus, an dem dieser Vertrag gelten soll.',
            ], 422);
        }

        $membershipPlan->update(['location_scope' => $validated['location_scope']]);
        $membershipPlan->allowedGyms()->sync(
            $validated['location_scope'] === MembershipPlan::SCOPE_SELECTED ? $allowed : []
        );

        return response()->json([
            'message' => 'Die Standorteinschränkungen des Vertrags wurden gespeichert.',
            'effect' => $this->crossLocationService->contractEffect(
                $gym,
                $validated['location_scope'],
                $allowed
            ),
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
            'original_price' => 'nullable|numeric|min:0|max:9999.99|gt:price',
            'setup_fee' => 'numeric|min:0|max:999.99',
            'billing_cycle' => 'required|in:monthly,quarterly,biannual,yearly',
            'is_active' => 'boolean',
            'commitment_months' => 'nullable|integer|min:0|max:36',
            'cancellation_period' => 'required|integer|min:0',
            'cancellation_period_unit' => 'required|in:days,months',
            'auto_renew_type' => 'nullable|in:indefinite,monthly',
            'start_date_mode' => 'nullable|in:next_possible,fixed',
            'fixed_start_date' => 'nullable|required_if:start_date_mode,fixed|date',
            ...MembershipPlanDiscountService::rules(),
            ...MembershipPlanAddonService::rules(),
        ]);

        // Additional validation based on unit
        if ($request->cancellation_period_unit === 'months' && $request->cancellation_period > 24) {
            return back()->withErrors(['cancellation_period' => 'Die Kündigungsfrist darf maximal 24 Monate betragen.'])->withInput();
        } elseif ($request->cancellation_period_unit === 'days' && $request->cancellation_period > 365) {
            return back()->withErrors(['cancellation_period' => 'Die Kündigungsfrist darf maximal 365 Tage betragen.'])->withInput();
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['commitment_months'] = $request->commitment_months ?? 0;
        $validated['auto_renew_type'] = $request->auto_renew_type ?? 'indefinite';
        $validated['start_date_mode'] = $request->start_date_mode ?? 'next_possible';
        $validated['fixed_start_date'] = $validated['start_date_mode'] === 'fixed'
            ? ($validated['fixed_start_date'] ?? null)
            : null;

        $discountPhases = $validated['discount_phases'] ?? [];
        $addonModes = $validated['addon_modes'] ?? [];
        unset($validated['discount_phases'], $validated['addon_modes']);
        // Discount phases run in months, so a non-monthly cycle turns them off
        // and MembershipPlanDiscountService::sync() then drops the phases.
        $validated['discounts_enabled'] = $request->boolean('discounts_enabled')
            && MembershipPlanDiscountService::supportsBillingCycle($validated['billing_cycle']);

        $membershipPlan->update($validated);

        $this->discountService->sync($membershipPlan, $discountPhases);
        $this->addonService->sync($membershipPlan, $addonModes);

        return Redirect::route('contracts.index')->with('flash', [
            'type' => 'success',
            'message' => 'Mitgliedschaftsplan wurde erfolgreich aktualisiert.',
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
                'message' => "Dieser Mitgliedschaftsplan kann nicht gelöscht werden, da noch {$activeMembersCount} aktive Mitglieder diesen nutzen: {$memberNames}{$additionalText}.",
            ]);
        }

        $membershipPlan->delete();

        return Redirect::route('contracts.index')->with('flash', [
            'type' => 'success',
            'message' => 'Mitgliedschaftsplan wurde erfolgreich gelöscht.',
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
                        'name' => $membership->member->first_name.' '.$membership->member->last_name,
                        'email' => $membership->member->email,
                    ];
                }),
            ]);
        }

        return response()->json([
            'canDelete' => true,
            'activeMembersCount' => 0,
            'activeMembers' => [],
        ]);
    }
}
