<?php

namespace App\Rules;

use App\Services\CssSanitizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeCss implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $validation = CssSanitizer::validate($value);

        if (! $validation['valid']) {
            $fail(__('validation.safe_css', [
                'errors' => implode(', ', $validation['errors']),
            ]));
        }
    }
}
