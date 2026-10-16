<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class RedeemSolariumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'minutes' => 'required|integer|min:1|max:60',
        ];
    }

    public function messages(): array
    {
        return [
            'minutes.*' => 'Bitte wähle zwischen 1 und 60 Minuten.',
        ];
    }
}
