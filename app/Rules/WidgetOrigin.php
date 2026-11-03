<?php

namespace App\Rules;

use App\Services\WidgetOriginService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Accepts "*" or anything WidgetOriginService can normalize to an http(s) origin.
 */
class WidgetOrigin implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || app(WidgetOriginService::class)->normalize($value) === null) {
            $fail('„'.(is_string($value) ? $value : '').'“ ist keine gültige Domain (z. B. https://www.ihr-studio.de).');
        }
    }
}
