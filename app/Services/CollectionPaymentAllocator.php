<?php

namespace App\Services;

use App\Models\CollectionCase;
use App\Models\CollectionClaim;
use Illuminate\Support\Collection;

/**
 * Distributes a payment across the open claims of a collection case.
 *
 * Automatic allocation always fills the oldest claim first, mirroring the
 * behaviour of the prototype.
 */
class CollectionPaymentAllocator
{
    /** Tolerance when comparing money sums, guards against float noise. */
    public const EPSILON = 0.005;

    /**
     * Open claims of a case, oldest due date first.
     *
     * @return Collection<int, CollectionClaim>
     */
    public function openClaims(CollectionCase $case): Collection
    {
        return $case->claims()
            ->where('written_off', false)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (CollectionClaim $claim) => (float) $claim->open_amount > self::EPSILON)
            ->values();
    }

    /**
     * Allocate an amount from the oldest to the newest claim.
     *
     * @return array<int, float> claim id => amount
     */
    public function allocateAutomatically(CollectionCase $case, float $amount): array
    {
        $remaining = $this->round($amount);
        $allocation = [];

        foreach ($this->openClaims($case) as $claim) {
            if ($remaining <= self::EPSILON) {
                break;
            }

            $take = min($remaining, (float) $claim->open_amount);
            $take = $this->round($take);

            if ($take > 0) {
                $allocation[$claim->id] = $take;
                $remaining = $this->round($remaining - $take);
            }
        }

        return $allocation;
    }

    /**
     * Normalise a manual allocation to the claims that actually belong to the
     * case, dropping zero amounts.
     *
     * @param  array<int|string, mixed>  $allocation
     * @return array<int, float>
     */
    public function normaliseManual(CollectionCase $case, array $allocation): array
    {
        $claimIds = $case->claims()->pluck('id')->all();
        $result = [];

        foreach ($allocation as $claimId => $amount) {
            $claimId = (int) $claimId;
            $amount = $this->round((float) $amount);

            if ($amount > 0 && in_array($claimId, $claimIds, true)) {
                $result[$claimId] = $amount;
            }
        }

        return $result;
    }

    /**
     * Whether the allocation adds up to the payment amount and no claim is
     * overpaid.
     *
     * @param  array<int, float>  $allocation
     */
    public function validate(CollectionCase $case, array $allocation, float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        if (abs($this->sum($allocation) - $this->round($amount)) > self::EPSILON) {
            return false;
        }

        $claims = $case->claims()->get()->keyBy('id');

        foreach ($allocation as $claimId => $allocated) {
            $claim = $claims->get($claimId);

            if (! $claim) {
                return false;
            }

            if ($allocated - (float) $claim->open_amount > self::EPSILON) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, float>  $allocation
     */
    public function sum(array $allocation): float
    {
        return $this->round(array_sum(array_map('floatval', $allocation)));
    }

    public function round(float $value): float
    {
        return round($value, 2);
    }
}
