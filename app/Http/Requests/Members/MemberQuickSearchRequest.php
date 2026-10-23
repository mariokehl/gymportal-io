<?php

namespace App\Http\Requests\Members;

use App\Models\Member;
use App\Services\MemberQuickSearchService;
use Illuminate\Foundation\Http\FormRequest;

class MemberQuickSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->current_gym_id !== null
            && $this->user()->can('viewAny', Member::class);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'ids' => ['nullable', 'array', 'max:'.MemberQuickSearchService::MAX_RECENT],
            'ids.*' => ['integer', 'min:1'],
        ];
    }
}
