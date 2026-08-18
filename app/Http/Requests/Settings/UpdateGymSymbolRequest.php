<?php

namespace App\Http\Requests\Settings;

use App\Models\Gym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGymSymbolRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller via the GymPolicy, so that
     * the check happens against the route-bound organisation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'symbol_type' => ['required', 'string', Rule::in(Gym::SYMBOL_TYPES)],
            // Restricted to the curated palette: the value is rendered as raw
            // text across the backend, so free-form input is not accepted.
            'symbol_emoji' => [
                'nullable',
                'required_if:symbol_type,'.Gym::SYMBOL_TYPE_EMOJI,
                'string',
                Rule::in(Gym::SYMBOL_EMOJIS),
            ],
            'symbol_color' => ['required', 'string', Rule::in(Gym::SYMBOL_COLORS)],
        ];
    }

    public function messages(): array
    {
        return [
            'symbol_type.in' => 'Die gewählte Symbolart wird nicht unterstützt.',
            'symbol_emoji.required_if' => 'Bitte wählen Sie ein Emoji aus.',
            'symbol_emoji.in' => 'Das gewählte Emoji steht nicht zur Auswahl.',
            'symbol_color.in' => 'Die gewählte Farbe steht nicht zur Auswahl.',
        ];
    }
}
