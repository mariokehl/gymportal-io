<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Membership;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Counts per calendar month for dashboards, with no person in the result:
 * joins, cancellations received, leavers, members at month end, churn rate
 * and, on request, the list fees of the running contracts.
 *
 * The member is the unit, so a change of plan is neither a join nor a leave,
 * and free trial plans do not count. Which contracts count as running:
 *
 * - a pending contract (widget sign-up not yet activated, presale) runs only
 *   once it is active; pending contracts are listed per plan on their own
 * - a withdrawn contract ends on the day of the withdrawal; one withdrawn
 *   before it began never ran, like a presale contract cancelled before its
 *   start or a pre-registration that ran into the pending timeout
 * - soft-deleted members, contracts and plans still count, so deleting a
 *   member does not rewrite past months
 *
 * A member is counted at the end of a month when a contract runs on its last
 * day and one still runs on the first day of the next month: whoever leaves on
 * the last day is gone, whoever starts on the first is a join of the next
 * month, and whoever changes plan at the turn of the month stays one member.
 *
 * List fees are the plan price per month, without discounts, add-ons, pauses
 * or actual payments: a measure of the contract volume, not revenue.
 */
class MonthlyInsightsService
{
    public const LIST_FEES_BASIS = 'plan price per month, without discounts, add-ons, pauses or actual payments; not revenue';

    private const ENDED = ['expired', 'canceled', 'cancelled'];

    private const MONTHS_PER_BILLING_CYCLE = ['quarterly' => 3, 'biannual' => 6, 'yearly' => 12];

    /**
     * @return array{months: list<array<string, int|float|string>>, contracts_not_ended_by_plan: array<string, int>, pending_contracts_by_plan: array<string, int>}
     */
    public function forGym(Gym $gym, int $months, CarbonInterface $today, bool $withListFees): array
    {
        $contracts = $this->contracts($gym);
        $running = $contracts->where('counts', true)->groupBy('member');
        $all = $contracts->groupBy('member');

        $rows = [];
        for ($i = $months; $i >= 0; $i--) {
            $rows[] = $this->month($running, $all, $today->copy()->startOfMonth()->subMonthsNoOverflow($i), $today);
        }

        $result = [];
        foreach (array_slice($rows, 1) as $k => $row) {
            $before = $rows[$k]['members_at_end'];
            $row['members_at_start'] = $before;
            $row['churn_rate_percent'] = $before > 0 ? round(100 * $row['leavers'] / $before, 1) : 0.0;
            foreach (['list_fees', 'list_fees_joined', 'list_fees_left'] as $key) {
                $row[$key] = round($row[$key], 2);
                if (! $withListFees) {
                    unset($row[$key]);
                }
            }
            $result[] = $row;
        }

        $notEnded = fn (array $c) => $c['exit'] === null || $c['exit'] >= $today->toDateString();

        return [
            'months' => $result,
            'contracts_not_ended_by_plan' => $contracts->where('counts', true)->filter($notEnded)->countBy('plan')->sortKeys()->all(),
            'pending_contracts_by_plan' => $contracts->where('pending', true)->filter($notEnded)->countBy('plan')->sortKeys()->all(),
        ];
    }

    /**
     * Every contract of the gym that is not a free trial, reduced to what the
     * counting needs. "counts" marks a contract that runs or ran as a
     * membership; pending and voided contracts stay in the list for the
     * cancellations received and the pending contracts per plan.
     *
     * @return Collection<int, array{member: int, start: string, exit: ?string, received: ?string, counts: bool, pending: bool, plan: ?string, monthly_fee: float}>
     */
    public function contracts(Gym $gym): Collection
    {
        return Membership::withTrashed()
            ->whereHas('member', fn ($q) => $q->withTrashed()->where('gym_id', $gym->id))
            ->with(['membershipPlan' => fn ($q) => $q->withTrashed()->select(['id', 'name', 'price', 'billing_cycle', 'is_free_trial_plan'])])
            ->get()
            ->map(fn (Membership $m) => $this->describe($m))
            ->filter()
            ->values();
    }

    /**
     * @return array{member: int, start: string, exit: ?string, received: ?string, counts: bool, pending: bool, plan: ?string, monthly_fee: float}|null
     */
    private function describe(Membership $m): ?array
    {
        $plan = $m->membershipPlan;
        $start = $this->date($m->getRawOriginal('start_date'));
        if ($start === null || $plan?->is_free_trial_plan) {
            return null;
        }

        $withdrawnAt = $this->date($m->getRawOriginal('withdrawn_at'));
        $cancelled = $this->date($m->getRawOriginal('cancellation_date'));
        $exit = match (true) {
            $m->status === 'withdrawn' || $withdrawnAt !== null => $withdrawnAt ?? $start,
            $cancelled !== null => $cancelled,
            in_array($m->status, self::ENDED, true) => $this->date($m->getRawOriginal('end_date')) ?? $start,
            default => null,
        };
        $pending = $m->status === 'pending';
        $timedOut = $m->status === 'expired' && $m->cancellation_reason === Membership::PENDING_TIMEOUT_REASON;

        return [
            'member' => $m->member_id,
            'start' => $start,
            'exit' => $exit,
            'received' => $this->date($m->getRawOriginal('cancellation_received_at')),
            // Whoever withdraws or cancels before the start was never a member: no join, no leaver, no fee
            'counts' => ! $pending && ! $timedOut && ($exit === null || $exit >= $start),
            'pending' => $pending,
            'plan' => $plan?->name,
            'monthly_fee' => $plan && $plan->price !== null
                ? (float) $plan->price / (self::MONTHS_PER_BILLING_CYCLE[$plan->billing_cycle] ?? 1)
                : 0.0,
        ];
    }

    /**
     * @param  Collection<int, Collection<int, array>>  $running  counting contracts per member
     * @param  Collection<int, Collection<int, array>>  $all  all contracts per member
     * @return array<string, int|float|string>
     */
    private function month(Collection $running, Collection $all, CarbonInterface $first, CarbonInterface $today): array
    {
        $from = $first->toDateString();
        $to = min($first->copy()->endOfMonth(), $today->copy())->toDateString();
        $current = $to === $today->toDateString();
        // Closed months: running on the last day and still on the first of the next month. The running month: today.
        $lastDay = $to;
        $nextDay = $current ? $to : $first->copy()->addMonthNoOverflow()->toDateString();

        $in = fn (?string $day) => $day !== null && $day >= $from && $day <= $to;
        $runs = fn (array $c, string $day) => $c['start'] <= $day && ($c['exit'] === null || $c['exit'] >= $day);

        $row = ['month' => $first->format('Y-m'), 'joins' => 0, 'cancellations_received' => 0, 'leavers' => 0, 'members_at_end' => 0,
            'list_fees' => 0.0, 'list_fees_joined' => 0.0, 'list_fees_left' => 0.0];

        foreach ($all as $contracts) {
            $row['cancellations_received'] += $contracts->contains(fn ($c) => $in($c['received'])) ? 1 : 0;
        }

        foreach ($running as $contracts) {
            $contracts = $contracts->all();
            $exits = array_column($contracts, 'exit');
            $row['joins'] += $in(min(array_column($contracts, 'start'))) ? 1 : 0;
            $row['leavers'] += ! in_array(null, $exits, true) && $in(max($exits)) ? 1 : 0;

            $atEnd = collect($contracts)->contains(fn ($c) => $runs($c, $lastDay))
                && collect($contracts)->contains(fn ($c) => $runs($c, $nextDay));
            $row['members_at_end'] += $atEnd ? 1 : 0;

            foreach ($contracts as $c) {
                $row['list_fees'] += $atEnd && $runs($c, $nextDay) ? $c['monthly_fee'] : 0;
                $row['list_fees_joined'] += $in($c['start']) ? $c['monthly_fee'] : 0;
                $row['list_fees_left'] += $in($c['exit']) ? $c['monthly_fee'] : 0;
            }
        }

        return $row;
    }

    private function date(mixed $value): ?string
    {
        return $value ? substr((string) $value, 0, 10) : null;
    }
}
