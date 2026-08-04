<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddonRequest;
use App\Http\Requests\UpdateAddonRequest;
use App\Models\Addon;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AddonController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the add-ons.
     */
    public function index(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $addons = Addon::where('gym_id', $user->current_gym_id)
            ->withCount('membershipPlans')
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Addons/Index', [
            'addons' => $addons,
            'flash' => session('flash'),
        ]);
    }

    /**
     * Show the form for creating a new add-on.
     */
    public function create(): Response
    {
        $this->authorize('create', Addon::class);

        return Inertia::render('Addons/Create', [
            'membershipPlans' => $this->membershipPlansForForm(),
            'paymentMethodOptions' => $this->paymentMethodOptions(),
        ]);
    }

    /**
     * Store a newly created add-on in storage.
     */
    public function store(StoreAddonRequest $request): RedirectResponse
    {
        $this->authorize('create', Addon::class);

        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validated();

        $addon = Addon::create([
            'gym_id' => $user->current_gym_id,
            ...$this->addonAttributes($request, $validated),
        ]);

        $this->syncPlanAssignments($addon, $request->input('plan_modes', []));

        return Redirect::route('contracts.addons.index')->with('flash', [
            'type' => 'success',
            'message' => 'Add-on wurde erfolgreich erstellt.',
        ]);
    }

    /**
     * Show the form for editing the specified add-on.
     */
    public function edit(Addon $addon): Response
    {
        $this->authorize('update', $addon);

        $addon->load('membershipPlans:id');

        // Build a { plan_id: mode } map of the current assignments.
        $planModes = $addon->membershipPlans
            ->mapWithKeys(fn ($plan) => [$plan->id => $plan->pivot->mode])
            ->all();

        return Inertia::render('Addons/Edit', [
            'addon' => $addon,
            'planModes' => $planModes,
            'membershipPlans' => $this->membershipPlansForForm(),
            'paymentMethodOptions' => $this->paymentMethodOptions(),
        ]);
    }

    /**
     * Update the specified add-on in storage.
     */
    public function update(UpdateAddonRequest $request, Addon $addon): RedirectResponse
    {
        $this->authorize('update', $addon);

        $validated = $request->validated();

        $addon->update($this->addonAttributes($request, $validated));

        $this->syncPlanAssignments($addon, $request->input('plan_modes', []));

        return Redirect::route('contracts.addons.index')->with('flash', [
            'type' => 'success',
            'message' => 'Add-on wurde erfolgreich aktualisiert.',
        ]);
    }

    /**
     * Remove the specified add-on from storage.
     */
    public function destroy(Addon $addon): RedirectResponse
    {
        $this->authorize('delete', $addon);

        $addon->delete();

        return Redirect::route('contracts.addons.index')->with('flash', [
            'type' => 'success',
            'message' => 'Add-on wurde erfolgreich gelöscht.',
        ]);
    }

    /**
     * Build the addon attributes from the validated payload. Fields that do not
     * apply to the chosen service or billing type are stored as null so that
     * switching the type never leaves stale values behind.
     */
    private function addonAttributes(StoreAddonRequest $request, array $validated): array
    {
        $isUsage = $validated['service_type'] === Addon::SERVICE_TYPE_USAGE;
        $isRecurring = $validated['billing_type'] === Addon::BILLING_TYPE_RECURRING;
        $hasFixedPeriod = $isUsage && ($validated['usage_period'] ?? null) === Addon::USAGE_PERIOD_FIXED;

        // An unlimited flat rate is expressed as a null quota amount.
        $quotaAmount = $isUsage ? ($validated['quota_amount'] ?? null) : null;

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],

            // A weekly display is only meaningful for monthly billing.
            'price_display' => $isRecurring
                ? ($validated['price_display'] ?? Addon::PRICE_DISPLAY_MONTHLY)
                : Addon::PRICE_DISPLAY_MONTHLY,

            'payment_method' => $validated['payment_method'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $validated['sort_order'] ?? 0,

            'service_type' => $validated['service_type'],
            'billing_type' => $validated['billing_type'],

            // The trial grants the rest of the month and only applies to
            // recurring billing.
            'trial_rest_of_month' => $isRecurring && $request->boolean('trial_rest_of_month'),

            'usage_period' => $isUsage ? ($validated['usage_period'] ?? null) : null,
            'usage_duration' => $hasFixedPeriod ? ($validated['usage_duration'] ?? null) : null,
            'usage_duration_unit' => $hasFixedPeriod ? ($validated['usage_duration_unit'] ?? null) : null,
            'quota_amount' => $quotaAmount,
            'quota_interval' => $quotaAmount !== null ? ($validated['quota_interval'] ?? null) : null,
            'settled_via_device' => $isUsage && $request->boolean('settled_via_device'),
        ];
    }

    /**
     * Sync the plan assignments from a { plan_id: mode } map. Entries with a
     * falsy / unset mode are treated as "not assigned" and removed.
     */
    private function syncPlanAssignments(Addon $addon, array $planModes): void
    {
        /** @var User $user */
        $user = Auth::user();

        // Only allow assigning plans that belong to the current gym.
        $validPlanIds = MembershipPlan::where('gym_id', $user->current_gym_id)
            ->pluck('id')
            ->all();

        $sync = [];
        foreach ($planModes as $planId => $mode) {
            if (! in_array((int) $planId, $validPlanIds, true)) {
                continue;
            }

            if (! in_array($mode, ['included', 'optional'], true)) {
                continue;
            }

            $sync[(int) $planId] = ['mode' => $mode];
        }

        $addon->membershipPlans()->sync($sync);
    }

    /**
     * Membership plans of the current gym for the assignment UI. Inactive plans
     * are included as well so that existing assignments stay visible and
     * editable; the UI filters by status.
     */
    private function membershipPlansForForm()
    {
        /** @var User $user */
        $user = Auth::user();

        return MembershipPlan::where('gym_id', $user->current_gym_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'billing_cycle', 'is_active']);
    }

    /**
     * Payment method options for the add-on payment method select.
     * An empty value means "use the member's default payment method".
     */
    private function paymentMethodOptions(): array
    {
        /** @var User $user */
        $user = Auth::user();

        $gym = Gym::find($user->current_gym_id);

        if (! $gym) {
            return [];
        }

        $options = [];
        foreach ($gym->getEnabledStandardPaymentMethods() as $method) {
            $options[] = [
                'key' => $method['key'],
                'name' => $method['name'],
            ];
        }

        return $options;
    }
}
