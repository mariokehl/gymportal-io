<?php

namespace App\Http\Requests\Finances;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinanceIndexRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [25, 50, 100, 250, 500];

    public const DEFAULT_PER_PAGE = 25;

    /**
     * Cookie remembering the page size a user picked last, per browser.
     */
    public const PER_PAGE_COOKIE = 'finances_per_page';

    public const PER_PAGE_COOKIE_MINUTES = 60 * 24 * 365;

    public function authorize(): bool
    {
        // Access to the finances area is guarded by the route middleware.
        return true;
    }

    /**
     * The requested page size, else the one remembered in the cookie, else
     * the default. The cookie is encrypted by Laravel and checked against
     * the options like the parameter.
     */
    public function perPage(): int
    {
        $perPage = (int) ($this->validated('per_page') ?? $this->cookie(self::PER_PAGE_COOKIE));

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }
}
