<?php

namespace App\Services;

use App\Models\MembershipPlan;
use Illuminate\Support\Facades\DB;

class MembershipPlanDiscountService
{
    /**
     * Validation rules for the discount phase rows submitted with a plan.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'discounts_enabled' => 'boolean',
            'discount_phases' => 'array|max:12',
            'discount_phases.*.duration_months' => 'required|integer|min:1|max:120',
            'discount_phases.*.price' => 'required|numeric|min:0|max:9999.99',
            'discount_phases.*.original_price' => 'nullable|numeric|min:0|max:9999.99',
        ];
    }

    /**
     * Replace a plan's discount phases with the submitted set.
     *
     * Phases are positional and always sent in full by the form, so a
     * delete-and-recreate keeps `sort_order` consistent without diffing.
     *
     * @param  array<int, array{duration_months: int|string, price: int|float|string, original_price?: int|float|string|null}>  $phases
     */
    public function sync(MembershipPlan $plan, array $phases): void
    {
        DB::transaction(function () use ($plan, $phases): void {
            $plan->discountPhases()->delete();

            if (! $plan->discounts_enabled) {
                return;
            }

            $rows = collect($phases)
                ->filter(fn (array $phase): bool => (int) ($phase['duration_months'] ?? 0) > 0)
                ->values()
                ->map(fn (array $phase, int $index): array => [
                    'sort_order' => $index,
                    'duration_months' => (int) $phase['duration_months'],
                    'price' => $phase['price'],
                    'original_price' => ($phase['original_price'] ?? '') === '' ? null : $phase['original_price'],
                ])
                ->all();

            if ($rows !== []) {
                $plan->discountPhases()->createMany($rows);
            }
        });
    }
}
