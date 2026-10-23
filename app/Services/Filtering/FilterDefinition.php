<?php

namespace App\Services\Filtering;

use Illuminate\Database\Eloquent\Builder;

/**
 * A filter of the filter builder: groups of conditions, combined with
 * AND/OR inside each group and between the groups, e.g. (A AND B) OR (C).
 *
 * Input from the browser or a saved filter is sanitized against the field
 * registry: unknown fields and operators are dropped, sizes are capped and
 * values are reduced to plain strings. The structure is never trusted.
 */
final class FilterDefinition
{
    public const MAX_GROUPS = 10;

    public const MAX_CONDITIONS_PER_GROUP = 20;

    public const MAX_VALUE_LENGTH = 100;

    private const LOGICS = ['AND', 'OR'];

    /**
     * @param  array{rootLogic: string, groups: list<array{logic: string, conditions: list<array>}>}  $definition
     * @param  array<string, FilterField>  $fields
     */
    private function __construct(
        private readonly array $definition,
        private readonly array $fields,
    ) {}

    /**
     * @param  array<string, FilterField>  $fields
     */
    public static function empty(array $fields): self
    {
        return new self(['rootLogic' => 'AND', 'groups' => []], $fields);
    }

    /**
     * Parses the JSON encoded filter of a request; malformed input yields
     * an empty filter instead of an error, like an unknown query parameter.
     *
     * @param  array<string, FilterField>  $fields
     */
    public static function fromJson(?string $json, array $fields): self
    {
        $input = $json ? json_decode($json, true, 8) : null;

        return is_array($input) ? self::fromArray($input, $fields) : self::empty($fields);
    }

    /**
     * @param  array<string, FilterField>  $fields
     */
    public static function fromArray(array $input, array $fields): self
    {
        $groups = collect(is_array($input['groups'] ?? null) ? $input['groups'] : [])
            ->filter(fn ($group) => is_array($group))
            ->take(self::MAX_GROUPS)
            ->map(fn (array $group) => [
                'logic' => self::logic($group['logic'] ?? null),
                'conditions' => collect(is_array($group['conditions'] ?? null) ? $group['conditions'] : [])
                    ->filter(fn ($condition) => is_array($condition))
                    ->take(self::MAX_CONDITIONS_PER_GROUP)
                    ->map(fn (array $condition) => self::condition($condition, $fields))
                    ->filter()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return new self(['rootLogic' => self::logic($input['rootLogic'] ?? null), 'groups' => $groups], $fields);
    }

    public function toArray(): array
    {
        return $this->definition;
    }

    /**
     * Number of conditions that take effect.
     */
    public function activeConditionCount(): int
    {
        return collect($this->activeGroups())->sum(fn (array $group) => count($group['conditions']));
    }

    public function apply(Builder $query): Builder
    {
        $groups = $this->activeGroups();

        if ($groups === []) {
            return $query;
        }

        $rootMethod = $this->definition['rootLogic'] === 'OR' ? 'orWhere' : 'where';

        return $query->where(function (Builder $rootQuery) use ($groups, $rootMethod) {
            foreach ($groups as $group) {
                $rootQuery->{$rootMethod}(function (Builder $groupQuery) use ($group) {
                    $conditionMethod = $group['logic'] === 'OR' ? 'orWhere' : 'where';

                    foreach ($group['conditions'] as $condition) {
                        $groupQuery->{$conditionMethod}(
                            fn (Builder $conditionQuery) => $this->fields[$condition['field']]->apply($conditionQuery, $condition)
                        );
                    }
                });
            }
        });
    }

    /**
     * Groups reduced to their complete conditions; empty groups drop out.
     */
    private function activeGroups(): array
    {
        return collect($this->definition['groups'])
            ->map(fn (array $group) => [
                ...$group,
                'conditions' => array_values(array_filter(
                    $group['conditions'],
                    fn (array $condition) => $this->fields[$condition['field']]->isComplete($condition)
                )),
            ])
            ->filter(fn (array $group) => $group['conditions'] !== [])
            ->values()
            ->all();
    }

    private static function logic(mixed $logic): string
    {
        return in_array($logic, self::LOGICS, true) ? $logic : 'AND';
    }

    /**
     * @param  array<string, FilterField>  $fields
     */
    private static function condition(array $condition, array $fields): ?array
    {
        $field = $fields[$condition['field'] ?? null] ?? null;
        $operator = $condition['op'] ?? null;

        if (! $field || ! is_string($operator) || ! $field->type->supports($operator)) {
            return null;
        }

        $values = collect(is_array($condition['values'] ?? null) ? $condition['values'] : [])
            ->filter(fn ($value) => is_scalar($value) && array_key_exists((string) $value, $field->options))
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();

        return [
            'id' => self::scalar($condition['id'] ?? null, 32),
            'field' => $field->key,
            'op' => $operator,
            'value' => self::scalar($condition['value'] ?? null),
            'value2' => self::scalar($condition['value2'] ?? null),
            'values' => $values,
        ];
    }

    private static function scalar(mixed $value, int $maxLength = self::MAX_VALUE_LENGTH): string
    {
        return is_scalar($value) ? mb_substr(trim((string) $value), 0, $maxLength) : '';
    }
}
