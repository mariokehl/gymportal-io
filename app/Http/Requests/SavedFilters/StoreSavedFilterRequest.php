<?php

namespace App\Http\Requests\SavedFilters;

use App\Models\Member;
use App\Services\SavedFilterService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->current_gym_id !== null
            && $this->user()->can('viewAny', Member::class);
    }

    public function rules(): array
    {
        return [
            'context' => ['required', 'string', Rule::in(SavedFilterService::CONTEXTS)],
            'name' => ['required', 'string', 'max:100'],
            'definition' => ['required', 'array'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Name des Filters'];
    }
}
