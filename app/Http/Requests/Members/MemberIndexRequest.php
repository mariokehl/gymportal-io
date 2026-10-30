<?php

namespace App\Http\Requests\Members;

use App\Models\Member;
use App\Services\MemberListService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->current_gym_id !== null
            && $this->user()->can('viewAny', Member::class);
    }

    /**
     * The requested page size, else the one remembered in the cookie, else
     * the default. The cookie is encrypted by Laravel and checked against
     * the options like the parameter.
     */
    public function perPage(): int
    {
        $perPage = (int) ($this->validated('perPage') ?? $this->cookie(MemberListService::PER_PAGE_COOKIE));

        return in_array($perPage, MemberListService::PER_PAGE_OPTIONS, true) ? $perPage : MemberListService::DEFAULT_PER_PAGE;
    }

    public function rules(): array
    {
        return [
            // JSON encoded filter definition; sanitized by FilterDefinition.
            'filter' => ['nullable', 'string', 'max:20000'],
            'sortBy' => ['nullable', Rule::in(MemberListService::SORTABLE_COLUMNS)],
            'sortDirection' => ['nullable', Rule::in(['asc', 'desc'])],
            'perPage' => ['nullable', 'integer', Rule::in(MemberListService::PER_PAGE_OPTIONS)],
        ];
    }
}
