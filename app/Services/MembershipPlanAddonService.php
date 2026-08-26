<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\MembershipPlan;
use Illuminate\Support\Collection;

class MembershipPlanAddonService
{
    /**
     * Assignment modes an add-on can hold on a plan. Anything else — including
     * the "not assigned" placeholder the form sends — detaches the add-on.
     */
    public const MODES = ['included', 'optional'];

    /**
     * Validation rules for the addon_modes map posted by the contract form.
     */
    public static function rules(): array
    {
        return [
            'addon_modes' => ['nullable', 'array'],
            'addon_modes.*' => ['nullable', 'in:included,optional'],
        ];
    }

    /**
     * Add-ons of the plan's gym, plus the mode each one currently holds on the
     * plan. Used to seed the assignment card on the contract form.
     */
    public function optionsForGym(int $gymId): Collection
    {
        return Addon::where('gym_id', $gymId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'price',
                'billing_type',
                'service_type',
                'price_display',
                'is_active',
            ]);
    }

    /**
     * Current { addon_id: mode } assignments of a plan.
     */
    public function modesFor(MembershipPlan $membershipPlan): array
    {
        return $membershipPlan->addons()
            ->get(['addons.id'])
            ->mapWithKeys(fn (Addon $addon) => [$addon->id => $addon->pivot->mode])
            ->all();
    }

    /**
     * Sync the plan's add-on assignments from a { addon_id: mode } map.
     *
     * Add-ons outside the plan's gym and unknown modes are dropped, so a
     * tampered payload cannot pull another gym's add-on onto the plan. A free
     * trial plan books no extras at all and is always cleared.
     */
    public function sync(MembershipPlan $membershipPlan, array $addonModes): void
    {
        if ($membershipPlan->is_free_trial_plan) {
            $membershipPlan->addons()->detach();

            return;
        }

        $validAddonIds = Addon::where('gym_id', $membershipPlan->gym_id)
            ->pluck('id')
            ->all();

        $assignments = [];

        foreach ($addonModes as $addonId => $mode) {
            if (! in_array((int) $addonId, $validAddonIds, true)) {
                continue;
            }

            if (! in_array($mode, self::MODES, true)) {
                continue;
            }

            $assignments[(int) $addonId] = ['mode' => $mode];
        }

        $membershipPlan->addons()->sync($assignments);
    }
}
