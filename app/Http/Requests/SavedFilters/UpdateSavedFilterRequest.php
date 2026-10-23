<?php

namespace App\Http\Requests\SavedFilters;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('savedFilter'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'definition' => ['required', 'array'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Name des Filters'];
    }
}
