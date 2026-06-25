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
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'payment_method' => $validated['payment_method'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $validated['sort_order'] ?? 0,
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

        $addon->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'payment_method' => $validated['payment_method'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

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
     * Active membership plans of the current gym for the assignment UI.
     */
    private function membershipPlansForForm()
    {
        /** @var User $user */
        $user = Auth::user();

        return MembershipPlan::where('gym_id', $user->current_gym_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'billing_cycle']);
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
