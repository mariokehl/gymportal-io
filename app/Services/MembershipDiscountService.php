<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipDiscountPhase;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Applies a membership's frozen discount phases to its billing.
 *
 * Revision safety is the point of this service: the phases are copied onto the
 * membership when the contract is signed (see snapshot()), and every charge
 * afterwards resolves its price from that copy. A studio operator editing the
 * plan's discount ladder therefore only affects contracts signed from then on,
 * never the ones already running.
 *
 * All arithmetic runs in integer cents — phased prices compound over many
 * months and binary floats would drift in amounts a member actually pays.
 */
class MembershipDiscountService
{
    public function __construct(
        private readonly DiscountProjectionService $projection
    ) {}

    /**
     * Copy the plan's discount phases onto a freshly created membership.
     *
     * Nothing is written when the plan has no active discounts, which leaves
     * the membership on the regular plan price — the behaviour of every
     * contract signed before discounts existed.
     */
    public function snapshot(Membership $membership, ?MembershipPlan $plan = null): void
    {
        $plan ??= $membership->membershipPlan;

        if (! $plan instanceof MembershipPlan) {
            return;
        }

        if (! $plan->discounts_enabled
            || ! MembershipPlanDiscountService::supportsBillingCycle($plan->billing_cycle)) {
            return;
        }

        $rows = $plan->discountPhases()
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($phase): bool => (int) $phase->duration_months > 0)
            ->values()
            ->map(fn ($phase, int $index): array => [
                'sort_order' => $index,
                // Ties the charge back to the exact ladder generation the
                // operator had configured when this contract was signed.
                'version_key' => $phase->version_key,
                'duration_months' => (int) $phase->duration_months,
                'price' => $phase->price,
                'original_price' => $phase->original_price,
            ])
            ->all();

        if ($rows === []) {
            return;
        }

        $membership->discountPhases()->createMany($rows);
    }

    /**
     * Price in cents owed for the billing period starting on $billingDate.
     *
     * Returns null when the membership has no frozen phases, or when the date
     * falls past the discounted period — the caller then charges the plan's
     * regular price, so an absent snapshot behaves exactly as before.
     */
    public function priceCentsFor(Membership $membership, Carbon $billingDate): ?int
    {
        $phases = $this->phasesFor($membership);

        if ($phases->isEmpty()) {
            return null;
        }

        $monthIndex = $this->monthIndex($membership, $billingDate);

        if ($monthIndex === null) {
            return null;
        }

        $monthsUsed = 0;

        foreach ($phases as $phase) {
            $monthsUsed += (int) $phase->duration_months;

            if ($monthIndex < $monthsUsed) {
                return $this->projection->toCents($phase->price);
            }
        }

        // Past the last phase: the regular plan price applies again.
        return null;
    }

    /**
     * Decimal amount owed for $billingDate, or null to charge the regular price.
     *
     * Payment amounts are stored as decimals, so this is the shape billing
     * code wants; priceCentsFor() stays available for arithmetic.
     */
    public function priceFor(Membership $membership, Carbon $billingDate): ?string
    {
        $cents = $this->priceCentsFor($membership, $billingDate);

        return $cents === null ? null : number_format($cents / 100, 2, '.', '');
    }

    /**
     * Audit block describing the discount applied to one charge, for the
     * payment's metadata. Null when the period is billed at the regular price.
     *
     * This is what makes a reduced amount legible to the operator: it names
     * the regular price it replaced, the months the phase covers and the
     * ladder version the contract was signed under.
     *
     * @return array{
     *     version_key: string|null,
     *     phase: int,
     *     phase_months: int,
     *     period_start_month: int,
     *     period_end_month: int,
     *     regular_price: string,
     *     discounted_price: string,
     *     savings: string
     * }|null
     */
    public function auditFor(Membership $membership, Carbon $billingDate, string|float|null $regularPrice): ?array
    {
        $phases = $this->phasesFor($membership);
        $monthIndex = $this->monthIndex($membership, $billingDate);

        if ($phases->isEmpty() || $monthIndex === null) {
            return null;
        }

        $monthsUsed = 0;

        foreach ($phases as $position => $phase) {
            $startMonth = $monthsUsed + 1;
            $monthsUsed += (int) $phase->duration_months;

            if ($monthIndex >= $monthsUsed) {
                continue;
            }

            $regularCents = $this->projection->toCents($regularPrice);
            $phaseCents = $this->projection->toCents($phase->price);

            return [
                'version_key' => $phase->version_key,
                'phase' => (int) $position + 1,
                'phase_months' => (int) $phase->duration_months,
                'period_start_month' => $startMonth,
                'period_end_month' => $monthsUsed,
                'regular_price' => number_format($regularCents / 100, 2, '.', ''),
                'discounted_price' => number_format($phaseCents / 100, 2, '.', ''),
                'savings' => number_format(max(0, $regularCents - $phaseCents) / 100, 2, '.', ''),
            ];
        }

        return null;
    }

    /**
     * Whether this membership carries a frozen discount at all.
     */
    public function hasSnapshot(Membership $membership): bool
    {
        return $this->phasesFor($membership)->isNotEmpty();
    }

    /**
     * Zero-based number of whole months between contract start and the billing
     * date. Null when the date precedes the contract, which no charge should.
     */
    private function monthIndex(Membership $membership, Carbon $billingDate): ?int
    {
        $start = $membership->start_date instanceof Carbon
            ? $membership->start_date->copy()
            : Carbon::parse($membership->start_date);

        $start = $start->startOfDay();
        $billingDate = $billingDate->copy()->startOfDay();

        if ($billingDate->lt($start)) {
            return null;
        }

        // startOfMonth keeps a contract starting on the 31st from losing a
        // month against a billing date on the 30th.
        $months = $start->copy()->startOfMonth()->diffInMonths($billingDate->copy()->startOfMonth());

        return (int) $months;
    }

    /**
     * @return Collection<int, MembershipDiscountPhase>
     */
    private function phasesFor(Membership $membership): Collection
    {
        if ($membership->relationLoaded('discountPhases')) {
            return $membership->getRelation('discountPhases');
        }

        return $membership->discountPhases()->orderBy('sort_order')->get();
    }
}
