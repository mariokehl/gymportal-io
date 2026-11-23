<?php

namespace App\Http\Requests;

use App\Enums\ThemePreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppearanceRequest extends FormRequest
{
    /**
     * Every signed-in user may change their own appearance; the controller
     * only ever updates the authenticated user.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'theme_preference' => ['required', Rule::enum(ThemePreference::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'theme_preference.required' => 'Bitte wählen Sie eine Darstellung.',
            'theme_preference.enum' => 'Diese Darstellung gibt es nicht.',
        ];
    }
}
