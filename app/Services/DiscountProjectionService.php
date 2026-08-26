<?php

namespace App\Services;

use App\Models\MembershipPlan;
use Illuminate\Support\Collection;

/**
 * Projects the price a member pays over the course of a contract with
 * promotional discount phases applied.
 *
 * All arithmetic runs in integer cents. Phased pricing compounds across many
 * months, so binary floats would accumulate drift in member-facing totals.
 *
 * IMPORTANT: this service is currently presentational — it powers the
 * "Preisverlauf" projection in the contract form. It is NOT yet consulted by
 * PaymentService or WidgetService, which still charge MembershipPlan::price
 * directly. Wiring the billing path to these phases is deliberately a separate
 * change.
 */
class DiscountProjectionService
{
    /**
     * Fallback horizon (months) when a plan has no minimum commitment, so the
     * projection still has a defined window to total up.
     */
    public const DEFAULT_TERM_MONTHS = 12;

    /**
     * Build the full projection for a plan: per-segment timeline, totals and
     * the member's saving over the considered term.
     *
     * @param  Collection<int, array{duration_months: int|string, price: int|float|string, original_price?: int|float|string|null}>|null  $phaseOverrides
     *                                                                                                                                                     Unsaved phases from the form, so the UI can project before persisting.
     * @return array{
     *     term_months: int,
     *     segments: list<array{duration_months: int, price_cents: int, is_discounted: bool, start_month: int, end_month: int}>,
     *     regular_total_cents: int,
     *     discounted_total_cents: int,
     *     savings_cents: int,
     *     discounted_months: int,
     *     exceeds_term: bool
     * }
     */
    public function project(MembershipPlan $plan, ?Collection $phaseOverrides = null): array
    {
        $regularPriceCents = $this->toCents($plan->price);
        $phases = $this->normalizePhases($phaseOverrides ?? $plan->discountPhases);

        $discountedMonths = $phases->sum('duration_months');
        $termMonths = $this->resolveTermMonths($plan, $discountedMonths);

        $segments = $this->buildSegments($phases, $termMonths, $regularPriceCents);

        $discountedTotalCents = collect($segments)
            ->sum(fn (array $segment): int => $segment['price_cents'] * $segment['duration_months']);

        $regularTotalCents = $regularPriceCents * $termMonths;

        return [
            'term_months' => $termMonths,
            'segments' => $segments,
            'regular_total_cents' => $regularTotalCents,
            'discounted_total_cents' => $discountedTotalCents,
            'savings_cents' => max(0, $regularTotalCents - $discountedTotalCents),
            'discounted_months' => $discountedMonths,
            'exceeds_term' => $plan->commitment_months > 0 && $discountedMonths > $plan->commitment_months,
        ];
    }

    /**
     * Term the projection is totalled over: the plan's minimum commitment when
     * set, otherwise enough months to cover every phase (falling back to a
     * default horizon) so the discounted period is never cut off.
     */
    private function resolveTermMonths(MembershipPlan $plan, int $discountedMonths): int
    {
        if ($plan->commitment_months > 0) {
            return max((int) $plan->commitment_months, $discountedMonths);
        }

        return max(self::DEFAULT_TERM_MONTHS, $discountedMonths);
    }

    /**
     * Discard blank/zero-length phases and coerce form input to a stable shape.
     *
     * @param  Collection<int, mixed>  $phases
     * @return Collection<int, array{duration_months: int, price_cents: int}>
     */
    private function normalizePhases(Collection $phases): Collection
    {
        return $phases
            ->map(function ($phase): array {
                $duration = (int) $this->attribute($phase, 'duration_months', 0);

                return [
                    'duration_months' => max(0, $duration),
                    'price_cents' => max(0, $this->toCents($this->attribute($phase, 'price', 0))),
                ];
            })
            ->filter(fn (array $phase): bool => $phase['duration_months'] > 0)
            ->values();
    }

    /**
     * Timeline segments across the term: each phase in order, then the regular
     * price for whatever remains. Phases are truncated at the term boundary.
     *
     * @param  Collection<int, array{duration_months: int, price_cents: int}>  $phases
     * @return list<array{duration_months: int, price_cents: int, is_discounted: bool, start_month: int, end_month: int}>
     */
    private function buildSegments(Collection $phases, int $termMonths, int $regularPriceCents): array
    {
        $segments = [];
        $monthsUsed = 0;

        foreach ($phases as $phase) {
            if ($monthsUsed >= $termMonths) {
                break;
            }

            $duration = min($phase['duration_months'], $termMonths - $monthsUsed);

            $segments[] = [
                'duration_months' => $duration,
                'price_cents' => $phase['price_cents'],
                'is_discounted' => true,
                'start_month' => $monthsUsed + 1,
                'end_month' => $monthsUsed + $duration,
            ];

            $monthsUsed += $duration;
        }

        if ($monthsUsed < $termMonths) {
            $segments[] = [
                'duration_months' => $termMonths - $monthsUsed,
                'price_cents' => $regularPriceCents,
                'is_discounted' => false,
                'start_month' => $monthsUsed + 1,
                'end_month' => $termMonths,
            ];
        }

        return $segments;
    }

    /**
     * Read a value from either an Eloquent model or a plain form array.
     */
    private function attribute(mixed $phase, string $key, mixed $default): mixed
    {
        if (is_array($phase)) {
            return $phase[$key] ?? $default;
        }

        return $phase->{$key} ?? $default;
    }

    /**
     * Convert a decimal amount to integer cents without a binary float round-trip.
     */
    public function toCents(string|int|float|null $amount): int
    {
        if ($amount === null) {
            return 0;
        }

        $normalized = preg_replace('/[^0-9,.\-]/', '', trim((string) $amount)) ?? '';

        if ($normalized === '' || $normalized === '-') {
            return 0;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            // The last-occurring separator is the decimal separator.
            $decimalSeparator = strrpos($normalized, ',') > strrpos($normalized, '.') ? ',' : '.';
            $normalized = str_replace($decimalSeparator === ',' ? '.' : ',', '', $normalized);
            $normalized = str_replace($decimalSeparator, '.', $normalized);
        } elseif ($hasComma) {
            // Single comma: German decimal input.
            $normalized = str_replace(',', '.', $normalized);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $sign = str_starts_with($whole, '-') ? -1 : 1;
        $whole = ltrim($whole, '+-');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return $sign * (((int) ($whole === '' ? '0' : $whole)) * 100 + (int) $fraction);
    }

    /**
     * Format integer cents as a German currency string, e.g. "1.234,50 €".
     */
    public function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }
}
