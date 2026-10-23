<?php

namespace App\Http\Requests\Members;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;

class ExportMembersRequest extends FormRequest
{
    public const MAX_IDS = 10000;

    public function authorize(): bool
    {
        return $this->user()->current_gym_id !== null
            && $this->user()->can('export', Member::class);
    }

    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'string', 'max:20000'],
            // Explicit selection; omitted when every match is exported.
            'member_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'member_ids.*' => ['integer', 'min:1'],
            // Matches deselected after "select all".
            'excluded_ids' => ['nullable', 'array', 'max:'.self::MAX_IDS],
            'excluded_ids.*' => ['integer', 'min:1'],
        ];
    }
}
