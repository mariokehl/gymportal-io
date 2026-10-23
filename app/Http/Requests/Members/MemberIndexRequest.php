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
