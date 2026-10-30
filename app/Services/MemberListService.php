<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Services\Filtering\FilterDefinition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The filtered member list of the admin UI and its CSV export.
 */
class MemberListService
{
    public const PER_PAGE_OPTIONS = [10, 25, 50];

    public const DEFAULT_PER_PAGE = 25;

    public const SORTABLE_COLUMNS = ['name', 'member_number', 'check_in_count', 'last_check_in', 'outstanding_balance'];

    private const CHECK_IN_COUNT_SQL = 'SELECT COUNT(*) FROM check_ins WHERE check_ins.member_id = members.id';

    private const LAST_CHECK_IN_SQL = 'SELECT MAX(check_ins.check_in_time) FROM check_ins WHERE check_ins.member_id = members.id';

    private const FAILED_PAYMENT_COUNT_SQL = "SELECT COUNT(*) FROM payments WHERE payments.member_id = members.id AND payments.status = 'failed'";

    public function __construct(private readonly MemberFilterFields $filterFields) {}

    public function definition(Gym $gym, ?string $json): FilterDefinition
    {
        return FilterDefinition::fromJson($json, $this->filterFields->forGym($gym));
    }

    /**
     * Members of the gym matching the filter, with the list columns.
     */
    public function query(Gym $gym, FilterDefinition $definition): Builder
    {
        $query = Member::query()
            ->where('members.gym_id', $gym->id)
            ->select('members.*')
            ->selectSub(Member::OUTSTANDING_BALANCE_SQL, 'outstanding_balance')
            ->selectSub(self::FAILED_PAYMENT_COUNT_SQL, 'failed_payment_count')
            ->selectSub(self::CHECK_IN_COUNT_SQL, 'check_in_count')
            ->selectSub(self::LAST_CHECK_IN_SQL, 'last_check_in_at')
            ->with([
                'memberships' => fn ($memberships) => $memberships
                    ->whereIn('memberships.status', MemberFilterFields::RUNNING_MEMBERSHIP_STATUSES)
                    ->orderBy('memberships.start_date')
                    ->with(['membershipPlan' => fn ($plan) => $plan->withTrashed()->select(['id', 'name'])]),
                'activePaymentMethods',
                'accessConfig',
                'nfcTags',
            ]);

        return $definition->apply($query);
    }

    public function paginate(Gym $gym, FilterDefinition $definition, string $sortBy, string $sortDirection, int $perPage): LengthAwarePaginator
    {
        $members = $this->sort($this->query($gym, $definition), $sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        $members->setCollection($members->getCollection()->map(fn (Member $member) => $this->toRow($member)));

        return $members;
    }

    public function sort(Builder $query, string $sortBy, string $sortDirection): Builder
    {
        // Both values are whitelisted by the request before they reach SQL.
        $direction = $sortDirection === 'desc' ? 'desc' : 'asc';

        match ($sortBy) {
            'name' => $query->orderBy('members.first_name', $direction)->orderBy('members.last_name', $direction),
            'check_in_count' => $query->orderByRaw('('.self::CHECK_IN_COUNT_SQL.') '.$direction),
            'last_check_in' => $query->orderByRaw('('.self::LAST_CHECK_IN_SQL.') IS NULL')
                ->orderByRaw('('.self::LAST_CHECK_IN_SQL.') '.$direction),
            'outstanding_balance' => $query->orderByRaw('('.Member::OUTSTANDING_BALANCE_SQL.') '.$direction),
            default => $query->orderBy('members.member_number', $direction),
        };

        return $query->orderBy('members.id');
    }

    /**
     * Payload of one table row; only what the list shows.
     */
    public function toRow(Member $member): array
    {
        $canDelete = $member->canBeDeleted();
        $deleteBlock = $canDelete ? null : $member->getDeleteBlockReason();
        $nfcTags = $this->nfcTags($member);

        return [
            'id' => $member->id,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'initials' => $member->initials,
            'age_verified' => $member->age_verified,
            'age_verified_at' => $member->age_verified_at?->toIso8601String(),
            'guest_access' => $member->guest_access,
            'member_number' => $member->member_number,
            'status' => $member->status,
            'salutation' => $member->salutation,
            'age' => $member->age,
            'postal_code' => $member->postal_code,
            'city' => $member->city,
            'plans' => $member->memberships->map(fn ($membership) => $membership->membershipPlan?->name)->filter()->unique()->values()->all(),
            'payment_method' => $this->paymentMethodLabel($this->paymentMethod($member)),
            'outstanding_balance' => (float) $member->outstanding_balance ?: null,
            'failed_payment_count' => (int) $member->failed_payment_count,
            'check_in_count' => (int) $member->check_in_count,
            'last_check_in_at' => $member->last_check_in_at,
            'nfc_tag' => $nfcTags[0] ?? null,
            'nfc_tag_count' => count($nfcTags),
            'aggregator' => $member->accessConfig?->aggregatorSummary(),
            'can_delete' => $canDelete,
            'delete_block_reason' => $canDelete ? null : ($deleteBlock['reason'] ?? 'Löschen nicht möglich'),
        ];
    }

    public function paymentMethod(Member $member): ?PaymentMethod
    {
        return $member->activePaymentMethods->sortByDesc('is_default')->first();
    }

    /**
     * Splits a label like "Mollie: SEPA-Lastschrift" into the method and its
     * provider, so the list can show the method and mark the provider apart.
     *
     * @return array{label: string, provider: ?string}|null
     */
    public function paymentMethodLabel(?PaymentMethod $paymentMethod): ?array
    {
        if (! $paymentMethod) {
            return null;
        }

        $parts = explode(': ', $paymentMethod->type_text, 2);

        return count($parts) === 2
            ? ['label' => $parts[1], 'provider' => $parts[0]]
            : ['label' => $parts[0], 'provider' => null];
    }

    /**
     * @return list<string>
     */
    public function nfcTags(Member $member): array
    {
        return collect([$member->accessConfig?->nfc_uid])
            ->merge($member->nfcTags->pluck('uid'))
            ->filter()
            ->values()
            ->all();
    }
}
