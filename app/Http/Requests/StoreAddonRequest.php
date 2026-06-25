<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller via the AddonPolicy.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:9999.99',
            'payment_method' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            // Per-plan assignment: keyed by membership_plan_id, value is the mode.
            'plan_modes' => 'nullable|array',
            'plan_modes.*' => ['nullable', Rule::in(['included', 'optional'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Bitte gib einen Namen für das Add-on an.',
            'price.required' => 'Bitte gib einen Preis an.',
            'price.numeric' => 'Der Preis muss eine Zahl sein.',
        ];
    }
}
