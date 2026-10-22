<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects birth dates that lie further back than a plausible human age.
 */
class MaxAge implements ValidationRule
{
    public const DEFAULT_YEARS = 100;

    public function __construct(private readonly int $years = self::DEFAULT_YEARS) {}

    /**
     * Earliest birth date accepted, as YYYY-MM-DD.
     */
    public static function earliestBirthDate(int $years = self::DEFAULT_YEARS): string
    {
        return now()->subYears($years)->format('Y-m-d');
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        // Unparseable values are left to the 'date' rule.
        try {
            $date = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        if ($date->lt(Carbon::parse(self::earliestBirthDate($this->years)))) {
            $fail("Das Geburtsdatum darf höchstens {$this->years} Jahre zurückliegen.");
        }
    }
}
