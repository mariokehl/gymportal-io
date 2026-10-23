<?php

namespace App\Services\Filtering;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * A field the filter builder offers, e.g. "Mitglied / Alter".
 *
 * The constraint closure receives a nested query and a condition that is
 * already sanitized and complete, so it only has to translate it into SQL.
 */
final readonly class FilterField
{
    /**
     * @param  array<string, string>  $options  value => label, for enum fields
     * @param  Closure(Builder, array): void  $constraint
     */
    public function __construct(
        public string $key,
        public string $entity,
        public string $label,
        public FilterType $type,
        public Closure $constraint,
        public array $options = [],
        public ?string $unit = null,
        public ?string $placeholder = null,
    ) {}

    public function apply(Builder $query, array $condition): void
    {
        ($this->constraint)($query, $condition);
    }

    /**
     * Whether the condition carries everything its operator needs. Incomplete
     * conditions stay in the definition (the UI keeps showing them) but are
     * ignored when the query is built.
     */
    public function isComplete(array $condition): bool
    {
        $operator = $condition['op'];
        $value = $condition['value'];
        $secondValue = $condition['value2'];

        return match ($this->type) {
            FilterType::Text => in_array($operator, ['empty', 'notempty'], true) || $value !== '',
            FilterType::Enum => $condition['values'] !== [],
            FilterType::Number => $operator === 'between'
                ? is_numeric($value) && is_numeric($secondValue)
                : is_numeric($value),
            FilterType::Count => match ($operator) {
                'has', 'none' => true,
                'between' => ctype_digit($value) && ctype_digit($secondValue),
                default => ctype_digit($value),
            },
            FilterType::Boolean => true,
            FilterType::Date => match ($operator) {
                'empty', 'notempty' => true,
                'lastDays', 'olderDays', 'nextDays' => ctype_digit($value),
                'between' => FilterConstraints::isDate($value) && FilterConstraints::isDate($secondValue),
                default => FilterConstraints::isDate($value),
            },
        };
    }

    /**
     * Field description for the filter builder in the frontend.
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'entity' => $this->entity,
            'label' => $this->label,
            'type' => $this->type->value,
            'options' => collect($this->options)
                ->map(fn (string $label, string|int $value) => ['value' => (string) $value, 'label' => $label])
                ->values()
                ->all(),
            'unit' => $this->unit,
            'placeholder' => $this->placeholder,
        ];
    }
}
