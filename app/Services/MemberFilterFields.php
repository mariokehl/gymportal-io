<?php

namespace App\Services;

use App\Enums\Aggregator;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Services\Filtering\FilterConstraints;
use App\Services\Filtering\FilterField;
use App\Services\Filtering\FilterType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Fields of the member filter builder.
 *
 * Every constraint runs inside a query that is already restricted to the
 * members of one gym; related tables are only reached through correlated
 * subqueries on members.id, so no condition can widen the tenant scope.
 */
class MemberFilterFields
{
    /**
     * Membership states that count as a running contract.
     */
    public const RUNNING_MEMBERSHIP_STATUSES = ['active', 'paused', 'pending'];

    public const SALUTATIONS = ['Herr' => 'Herr', 'Frau' => 'Frau', 'Divers' => 'Divers'];

    /** @var array<int, array<string, FilterField>> */
    private array $fieldsByGym = [];

    /**
     * @return array<string, FilterField>
     */
    public function forGym(Gym $gym): array
    {
        return $this->fieldsByGym[$gym->id] ??= collect($this->build($gym))
            ->keyBy(fn (FilterField $field) => $field->key)
            ->all();
    }

    /**
     * Field descriptions for the filter builder in the frontend.
     */
    public function schema(Gym $gym): array
    {
        return array_values(array_map(fn (FilterField $field) => $field->toArray(), $this->forGym($gym)));
    }

    /**
     * @return list<FilterField>
     */
    private function build(Gym $gym): array
    {
        return [
            $this->text('name', 'Mitglied', 'Name', "CONCAT(members.first_name, ' ', members.last_name)"),
            $this->text('email', 'Mitglied', 'E-Mail', 'members.email'),
            $this->text('member_number', 'Mitglied', 'Mitgliedsnummer', 'members.member_number'),
            new FilterField('status', 'Mitglied', 'Status', FilterType::Enum,
                fn (Builder $query, array $condition) => $condition['op'] === 'any'
                    ? $query->whereIn('members.status', $condition['values'])
                    : $query->whereNotIn('members.status', $condition['values']),
                options: Member::STATUS_LABELS,
            ),
            new FilterField('salutation', 'Mitglied', 'Anrede', FilterType::Enum,
                fn (Builder $query, array $condition) => $condition['op'] === 'any'
                    ? $query->whereIn('members.salutation', $condition['values'])
                    : $query->where(fn (Builder $inner) => $inner
                        ->whereNotIn('members.salutation', $condition['values'])
                        ->orWhereNull('members.salutation')),
                options: self::SALUTATIONS,
            ),
            new FilterField('age', 'Mitglied', 'Alter', FilterType::Number,
                fn (Builder $query, array $condition) => $this->constrainAge($query, $condition),
                unit: 'Jahre',
            ),
            $this->text('postal_code', 'Mitglied', 'PLZ', 'members.postal_code', 'z. B. 603'),
            $this->text('city', 'Mitglied', 'Ort', 'members.city'),
            new FilterField('joined_date', 'Mitglied', 'Eintrittsdatum', FilterType::Date,
                fn (Builder $query, array $condition) => FilterConstraints::date($query, 'members.joined_date', $condition),
            ),

            new FilterField('membership_count', 'Mitgliedschaft', 'Laufende Mitgliedschaften', FilterType::Count,
                fn (Builder $query, array $condition) => FilterConstraints::count($query, $this->runningMemberships(...), $condition),
            ),
            new FilterField('membership_plan', 'Mitgliedschaft', 'Tarif (laufend)', FilterType::Enum,
                fn (Builder $query, array $condition) => FilterConstraints::exists(
                    $query,
                    fn (QueryBuilder $subQuery) => $this->runningMemberships($subQuery)
                        ->whereIn('memberships.membership_plan_id', array_map('intval', $condition['values'])),
                    $condition['op'] === 'any',
                ),
                options: $this->planOptions($gym),
            ),
            new FilterField('contract_end', 'Mitgliedschaft', 'Vertragsende (gekündigt zum)', FilterType::Date,
                fn (Builder $query, array $condition) => $this->constrainAggregatedDate(
                    $query, $this->cancelledActiveMemberships(...), 'memberships.cancellation_date', $condition
                ),
            ),

            $this->boolean('has_outstanding_balance', 'Zahlungen', 'Hat offene Posten',
                fn (QueryBuilder $subQuery) => Member::unsettledChargebacks($subQuery)),
            new FilterField('outstanding_balance', 'Zahlungen', 'Summe offene Posten', FilterType::Number,
                fn (Builder $query, array $condition) => FilterConstraints::number(
                    $query, '('.Member::OUTSTANDING_BALANCE_SQL.')', $condition
                ),
                unit: '€',
            ),
            $this->boolean('has_failed_payments', 'Zahlungen', 'Hat fehlgeschlagene Zahlungen', $this->failedPayments(...)),
            new FilterField('failed_payment_count', 'Zahlungen', 'Fehlgeschlagene Zahlungen', FilterType::Count,
                fn (Builder $query, array $condition) => FilterConstraints::count($query, $this->failedPayments(...), $condition),
            ),
            new FilterField('payment_method', 'Zahlungen', 'Zahlungsart', FilterType::Enum,
                fn (Builder $query, array $condition) => FilterConstraints::exists(
                    $query,
                    fn (QueryBuilder $subQuery) => $subQuery->from('payment_methods')
                        ->whereColumn('payment_methods.member_id', 'members.id')
                        ->whereNull('payment_methods.deleted_at')
                        ->where('payment_methods.status', 'active')
                        ->whereIn('payment_methods.type', $condition['values']),
                    $condition['op'] === 'any',
                ),
                options: $this->paymentMethodOptions($gym),
            ),

            new FilterField('check_in_count', 'Check-Ins', 'Anzahl Check-Ins', FilterType::Count,
                fn (Builder $query, array $condition) => FilterConstraints::count($query, $this->checkIns(...), $condition),
            ),
            new FilterField('last_check_in', 'Check-Ins', 'Letzter Check-In', FilterType::Date,
                fn (Builder $query, array $condition) => $this->constrainAggregatedDate(
                    $query, $this->checkIns(...), 'check_ins.check_in_time', $condition
                ),
            ),

            $this->boolean('has_nfc_tag', 'Zugang', 'Hat NFC-Tag', $this->nfcAccessConfigs(...)),
            new FilterField('nfc_tag', 'Zugang', 'NFC-Tag ID', FilterType::Text,
                fn (Builder $query, array $condition) => $this->constrainNfcTag($query, $condition),
                placeholder: 'z. B. 04A32F1B',
            ),
            $this->boolean('has_aggregator', 'Zugang', 'Nutzt Aggregator',
                fn (QueryBuilder $subQuery) => $this->accessConfigs($subQuery)->whereNotNull('member_access_configs.aggregator')),
            new FilterField('aggregator', 'Zugang', 'Aggregator', FilterType::Enum,
                fn (Builder $query, array $condition) => FilterConstraints::exists(
                    $query,
                    fn (QueryBuilder $subQuery) => $this->accessConfigs($subQuery)
                        ->whereIn('member_access_configs.aggregator', $condition['values']),
                    $condition['op'] === 'any',
                ),
                options: collect(Aggregator::cases())->mapWithKeys(fn (Aggregator $aggregator) => [
                    $aggregator->value => $aggregator->label(),
                ])->all(),
            ),
        ];
    }

    private function text(string $key, string $entity, string $label, string $expression, ?string $placeholder = null): FilterField
    {
        return new FilterField($key, $entity, $label, FilterType::Text,
            fn (Builder $query, array $condition) => FilterConstraints::text($query, $expression, $condition),
            placeholder: $placeholder,
        );
    }

    private function boolean(string $key, string $entity, string $label, callable $related): FilterField
    {
        return new FilterField($key, $entity, $label, FilterType::Boolean,
            fn (Builder $query, array $condition) => FilterConstraints::exists($query, $related(...), $condition['op'] === 'yes'),
        );
    }

    private function runningMemberships(QueryBuilder $query): QueryBuilder
    {
        return $query->from('memberships')
            ->whereColumn('memberships.member_id', 'members.id')
            ->whereNull('memberships.deleted_at')
            ->whereIn('memberships.status', self::RUNNING_MEMBERSHIP_STATUSES);
    }

    /**
     * Active memberships that already have an end date — the same source as
     * the "Vertragsende" shown for a member.
     */
    private function cancelledActiveMemberships(QueryBuilder $query): QueryBuilder
    {
        return $query->from('memberships')
            ->whereColumn('memberships.member_id', 'members.id')
            ->whereNull('memberships.deleted_at')
            ->where('memberships.status', 'active')
            ->whereNotNull('memberships.cancellation_date');
    }

    private function failedPayments(QueryBuilder $query): QueryBuilder
    {
        return $query->from('payments')
            ->whereColumn('payments.member_id', 'members.id')
            ->where('payments.status', 'failed');
    }

    private function checkIns(QueryBuilder $query): QueryBuilder
    {
        return $query->from('check_ins')->whereColumn('check_ins.member_id', 'members.id');
    }

    private function accessConfigs(QueryBuilder $query): QueryBuilder
    {
        return $query->from('member_access_configs')->whereColumn('member_access_configs.member_id', 'members.id');
    }

    /**
     * Access configs holding at least one NFC tag, either the primary UID
     * or an additional tag.
     */
    private function nfcAccessConfigs(QueryBuilder $query): QueryBuilder
    {
        return $this->accessConfigs($query)->where(fn (QueryBuilder $tags) => $tags
            ->whereNotNull('member_access_configs.nfc_uid')
            ->orWhereExists(fn (QueryBuilder $additional) => $additional->select(DB::raw(1))
                ->from('member_nfc_tags')
                ->whereColumn('member_nfc_tags.member_access_config_id', 'member_access_configs.id')));
    }

    private function constrainNfcTag(Builder $query, array $condition): void
    {
        if (in_array($condition['op'], ['empty', 'notempty'], true)) {
            FilterConstraints::exists($query, $this->nfcAccessConfigs(...), $condition['op'] === 'notempty');

            return;
        }

        FilterConstraints::exists($query, fn (QueryBuilder $subQuery) => $this->accessConfigs($subQuery)
            ->where(fn (QueryBuilder $uids) => $uids
                ->where(fn (QueryBuilder $primary) => FilterConstraints::text($primary, 'member_access_configs.nfc_uid', $condition))
                ->orWhereExists(function (QueryBuilder $additional) use ($condition) {
                    $additional->select(DB::raw(1))
                        ->from('member_nfc_tags')
                        ->whereColumn('member_nfc_tags.member_access_config_id', 'member_access_configs.id');
                    FilterConstraints::text($additional, 'member_nfc_tags.uid', $condition);
                })));
    }

    /**
     * Date filter on the latest value of related rows (e.g. the last check-in).
     * "empty" means there is no such row at all.
     */
    private function constrainAggregatedDate(Builder $query, callable $related, string $column, array $condition): void
    {
        if (in_array($condition['op'], ['empty', 'notempty'], true)) {
            FilterConstraints::exists($query, $related(...), $condition['op'] === 'notempty');

            return;
        }

        FilterConstraints::date($query, function (QueryBuilder $subQuery) use ($related, $column) {
            $related($subQuery)->selectRaw("MAX({$column})");
        }, $condition);
    }

    /**
     * The age is derived from birth_date with date bounds, so the query
     * stays portable and can use an index on birth_date.
     */
    private function constrainAge(Builder $query, array $condition): void
    {
        $today = CarbonImmutable::today();
        $bornBy = fn (int $years) => $today->subYears($years)->toDateString();
        $atLeast = fn (int $years) => $query->where('members.birth_date', '<=', $bornBy($years));
        $atMost = fn (int $years) => $query->where('members.birth_date', '>', $bornBy($years + 1));
        $age = (int) floor((float) $condition['value']);
        $secondAge = (int) floor((float) $condition['value2']);

        match ($condition['op']) {
            'eq' => [$atLeast($age), $atMost($age)],
            'neq' => $query->whereNotNull('members.birth_date')->where(fn (Builder $inner) => $inner
                ->where('members.birth_date', '>', $bornBy($age))
                ->orWhere('members.birth_date', '<=', $bornBy($age + 1))),
            'gt' => $atLeast($age + 1),
            'gte' => $atLeast($age),
            'lt' => $atMost($age - 1),
            'lte' => $atMost($age),
            'between' => [$atLeast(min($age, $secondAge)), $atMost(max($age, $secondAge))],
        };
    }

    /**
     * Plans of the gym plus every plan its members hold a membership of
     * (location-wide plans may belong to a sibling gym).
     *
     * @return array<string, string>
     */
    private function planOptions(Gym $gym): array
    {
        $memberPlanIds = Membership::query()
            ->select('membership_plan_id')
            ->whereIn('member_id', Member::query()->select('id')->where('gym_id', $gym->id));

        return MembershipPlan::query()
            ->where(fn (Builder $query) => $query->where('gym_id', $gym->id)->orWhereIn('id', $memberPlanIds))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id) => [(string) $id => $name])
            ->all();
    }

    /**
     * Payment methods offered by the gym plus every type its members use.
     *
     * @return array<string, string>
     */
    private function paymentMethodOptions(Gym $gym): array
    {
        $options = collect($gym->getAllPaymentMethods())
            ->mapWithKeys(fn (array $method) => [$method['key'] => $method['name'] ?? $method['key']]);

        PaymentMethod::query()
            ->whereIn('member_id', Member::query()->select('id')->where('gym_id', $gym->id))
            ->distinct()
            ->pluck('type')
            ->each(function (string $type) use ($options) {
                if (! $options->has($type)) {
                    $options->put($type, (new PaymentMethod)->forceFill(['type' => $type])->type_text);
                }
            });

        return $options->sort()->all();
    }
}
