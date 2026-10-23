<?php

namespace App\Services\Filtering;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Translates the generic operators of a filter type into query constraints.
 *
 * SQL expressions passed in as strings are defined by the field registries
 * in code and are never derived from user input; every user value is bound.
 * All SQL is kept portable between MariaDB (development) and PostgreSQL
 * (production).
 */
class FilterConstraints
{
    public static function text(Builder|QueryBuilder $query, string $expression, array $condition): void
    {
        $value = mb_strtolower($condition['value']);

        match ($condition['op']) {
            'contains' => $query->whereRaw("LOWER({$expression}) LIKE ? ESCAPE '!'", ['%'.self::escapeLike($value).'%']),
            'starts' => $query->whereRaw("LOWER({$expression}) LIKE ? ESCAPE '!'", [self::escapeLike($value).'%']),
            'eq' => $query->whereRaw("LOWER({$expression}) = ?", [$value]),
            'empty' => $query->whereRaw("({$expression} IS NULL OR {$expression} = '')"),
            'notempty' => $query->whereRaw("({$expression} IS NOT NULL AND {$expression} <> '')"),
        };
    }

    /**
     * @param  string|Closure(QueryBuilder): mixed  $target  column/SQL expression or scalar subquery
     */
    public static function number(Builder $query, string|Closure $target, array $condition): void
    {
        $target = is_string($target) ? DB::raw($target) : $target;
        $value = self::numeric($condition['value']);

        if ($condition['op'] === 'between') {
            $secondValue = self::numeric($condition['value2']);
            $query->where($target, '>=', min($value, $secondValue))
                ->where($target, '<=', max($value, $secondValue));

            return;
        }

        $query->where($target, self::comparison($condition['op']), $value);
    }

    /**
     * Number of related rows. $related selects the rows (from + where) of
     * the relation for the current member, without a select clause.
     *
     * @param  Closure(QueryBuilder): mixed  $related
     */
    public static function count(Builder $query, Closure $related, array $condition): void
    {
        if (in_array($condition['op'], ['has', 'none'], true)) {
            self::exists($query, $related, $condition['op'] === 'has');

            return;
        }

        $countQuery = function (QueryBuilder $subQuery) use ($related) {
            $related($subQuery);
            $subQuery->selectRaw('COUNT(*)');
        };

        self::number($query, $countQuery, [
            ...$condition,
            'value' => (int) $condition['value'],
            'value2' => (int) $condition['value2'],
        ]);
    }

    /**
     * @param  Closure(QueryBuilder): mixed  $related
     */
    public static function exists(Builder|QueryBuilder $query, Closure $related, bool $positive = true): void
    {
        $existsQuery = function (QueryBuilder $subQuery) use ($related) {
            $related($subQuery);
            $subQuery->select(DB::raw(1));
        };

        $positive ? $query->whereExists($existsQuery) : $query->whereNotExists($existsQuery);
    }

    /**
     * Date or timestamp comparison. Every bound is a day boundary, so the
     * same constraint works for DATE and TIMESTAMP columns. "empty" and
     * "notempty" are only handled for plain columns; aggregated dates
     * resolve them through an existence check in their field.
     *
     * @param  string|Closure(QueryBuilder): mixed  $target  column or scalar subquery
     */
    public static function date(Builder $query, string|Closure $target, array $condition): void
    {
        if (in_array($condition['op'], ['empty', 'notempty'], true)) {
            $condition['op'] === 'empty' ? $query->whereNull($target) : $query->whereNotNull($target);

            return;
        }

        $today = CarbonImmutable::today();
        $value = $condition['value'];

        [$from, $until] = match ($condition['op']) {
            'lastDays' => [$today->subDays((int) $value), $today->addDay()],
            'olderDays' => [null, $today->subDays((int) $value)],
            'nextDays' => [$today, $today->addDays((int) $value + 1)],
            'before' => [null, self::day($value)],
            'after' => [self::day($value)->addDay(), null],
            'between' => [
                min(self::day($value), self::day($condition['value2'])),
                max(self::day($value), self::day($condition['value2']))->addDay(),
            ],
        };

        if ($from) {
            $query->where($target, '>=', $from->toDateTimeString());
        }

        if ($until) {
            $query->where($target, '<', $until->toDateTimeString());
        }
    }

    public static function isDate(string $value): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    /**
     * Escapes LIKE wildcards with "!", which needs no quoting in any of the
     * supported databases (a backslash would differ between them).
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * Whole numbers are bound as integers: PDO binds floats as strings,
     * which SQLite would compare as text and PostgreSQL could not cast to
     * the bigint of a COUNT(*).
     */
    private static function numeric(mixed $value): int|float
    {
        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }

    private static function comparison(string $operator): string
    {
        return match ($operator) {
            'eq' => '=',
            'neq' => '<>',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
        };
    }

    private static function day(string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $value);
    }
}
