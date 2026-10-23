<?php

namespace App\Services\Filtering;

/**
 * Value types of a filterable field. The type decides which operators a
 * condition may use and which inputs the filter builder renders.
 */
enum FilterType: string
{
    case Text = 'text';
    case Enum = 'enum';
    case Number = 'number';
    case Count = 'count';
    case Boolean = 'bool';
    case Date = 'date';

    /**
     * @return list<string>
     */
    public function operators(): array
    {
        return match ($this) {
            self::Text => ['contains', 'eq', 'starts', 'empty', 'notempty'],
            self::Enum => ['any', 'none'],
            self::Number => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between'],
            self::Count => ['has', 'none', 'eq', 'gte', 'lte', 'between'],
            self::Boolean => ['yes', 'no'],
            self::Date => ['lastDays', 'olderDays', 'nextDays', 'before', 'after', 'between', 'empty', 'notempty'],
        };
    }

    public function supports(string $operator): bool
    {
        return in_array($operator, $this->operators(), true);
    }
}
