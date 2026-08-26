<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\MembershipPlanDiscountPhase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembershipPlanDiscountService
{
    /**
     * Discount phases are expressed in months, so they only line up with a
     * billing cycle that is itself monthly.
     */
    public const SUPPORTED_BILLING_CYCLE = 'monthly';

    /**
     * Whether a plan on this billing cycle may carry discount phases.
     */
    public static function supportsBillingCycle(?string $billingCycle): bool
    {
        return $billingCycle === self::SUPPORTED_BILLING_CYCLE;
    }

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
     * delete-and-recreate keeps `sort_order` consistent without diffing. The
     * replaced rows are soft-deleted rather than dropped, so the version key a
     * signed contract points at stays resolvable.
     *
     * Saving the contract without touching the ladder keeps the current
     * version — a new key is only minted when the phases actually differ.
     *
     * @param  array<int, array{duration_months: int|string, price: int|float|string, original_price?: int|float|string|null}>  $phases
     */
    public function sync(MembershipPlan $plan, array $phases): void
    {
        DB::transaction(function () use ($plan, $phases): void {
            $current = $plan->discountPhases()->orderBy('sort_order')->get();

            $plan->discountPhases()->delete();

            if (! $plan->discounts_enabled || ! self::supportsBillingCycle($plan->billing_cycle)) {
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

            if ($rows === []) {
                return;
            }

            $versionKey = $this->resolveVersionKey($current, $rows);

            if ($this->fingerprint($current) === $this->fingerprint(collect($rows))) {
                // Unchanged ladder: restore the rows that carry this version
                // instead of stacking up an identical generation.
                $plan->discountPhases()->onlyTrashed()->where('version_key', $versionKey)->restore();

                return;
            }

            $plan->discountPhases()->createMany(
                array_map(
                    fn (array $row): array => [...$row, 'version_key' => $versionKey],
                    $rows
                )
            );
        });
    }

    /**
     * The price ladder a customer runs through over the contract term.
     *
     * Every discount phase becomes one segment covering the months it spans;
     * a final open-ended segment carries the plan's regular price. Plans
     * without an active ladder collapse to that single regular segment, so
     * callers can treat both cases the same way.
     *
     * @return array<int, array{from: int, to: int|null, price: float, original_price: float|null, promo: bool}>
     */
    public function segmentsFor(MembershipPlan $plan): array
    {
        $segments = [];
        $month = 1;

        // A phase without its own reference price is measured against the
        // plan's UVP — that is what the phase editor offers as the placeholder,
        // so leaving the field empty means "same as the plan".
        $planOriginal = $plan->original_price === null ? null : (float) $plan->original_price;

        foreach ($this->activePhasesFor($plan) as $phase) {
            $segments[] = [
                'from' => $month,
                'to' => $month + $phase->duration_months - 1,
                'price' => (float) $phase->price,
                'original_price' => $phase->original_price === null
                    ? $planOriginal
                    : (float) $phase->original_price,
                'promo' => true,
            ];

            $month += $phase->duration_months;
        }

        $segments[] = [
            'from' => $month,
            'to' => null,
            'price' => (float) $plan->price,
            'original_price' => $plan->original_price === null ? null : (float) $plan->original_price,
            'promo' => false,
        ];

        return $segments;
    }

    /**
     * The phases that actually apply: an enabled ladder on a monthly plan.
     *
     * @return Collection<int, MembershipPlanDiscountPhase>
     */
    public function activePhasesFor(MembershipPlan $plan): Collection
    {
        if (! $plan->discounts_enabled || ! self::supportsBillingCycle($plan->billing_cycle)) {
            return collect();
        }

        return $plan->discountPhases->sortBy('sort_order')->values();
    }

    /**
     * The price the customer pays first, plus how it compares to the regular
     * one. This is the figure the plan card and the checkout headline show.
     *
     * @return array{price: float, original_price: float|null, has_discount: bool, discount_percent: int|null}
     */
    public function entryPriceFor(MembershipPlan $plan): array
    {
        $first = $this->segmentsFor($plan)[0];
        $original = $first['original_price'];
        $hasDiscount = $original !== null && $original > $first['price'];

        return [
            'price' => $first['price'],
            'original_price' => $original,
            'has_discount' => $hasDiscount,
            'discount_percent' => $hasDiscount
                ? (int) round((1 - $first['price'] / $original) * 100)
                : null,
        ];
    }

    /**
     * Sum of the monthly contributions over the initial term, with every
     * discount phase charged for the months it covers. Excludes the setup fee
     * and any add-ons — those are added by the caller.
     */
    public function contractTotalFor(MembershipPlan $plan): float
    {
        $segments = $this->segmentsFor($plan);
        $total = 0.0;

        for ($month = 1; $month <= $plan->commitment_months; $month++) {
            foreach ($segments as $segment) {
                if ($month >= $segment['from'] && ($segment['to'] === null || $month <= $segment['to'])) {
                    $total += $segment['price'];
                    break;
                }
            }
        }

        return round($total, 2);
    }

    /**
     * Keep the current version when the ladder is unchanged, otherwise mint a
     * new one so contracts signed before and after are told apart.
     *
     * @param  Collection<int, mixed>  $current
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function resolveVersionKey(Collection $current, array $rows): string
    {
        $existingKey = $current->first()?->version_key;

        if ($existingKey && $this->fingerprint($current) === $this->fingerprint(collect($rows))) {
            return (string) $existingKey;
        }

        return (string) Str::uuid();
    }

    /**
     * Comparable representation of a ladder, so two generations can be checked
     * for equality regardless of how the values were typed on the way in.
     *
     * @param  Collection<int, mixed>  $phases
     */
    private function fingerprint(Collection $phases): string
    {
        return $phases
            ->map(function ($phase): string {
                $duration = (int) $this->value($phase, 'duration_months');
                $price = number_format((float) $this->value($phase, 'price'), 2, '.', '');
                $original = $this->value($phase, 'original_price');
                $original = ($original === null || $original === '')
                    ? '-'
                    : number_format((float) $original, 2, '.', '');

                return "{$duration}:{$price}:{$original}";
            })
            ->implode('|');
    }

    /**
     * Read a field from either a persisted phase or a submitted row.
     */
    private function value(mixed $phase, string $key): mixed
    {
        return is_array($phase) ? ($phase[$key] ?? null) : ($phase->{$key} ?? null);
    }
}
