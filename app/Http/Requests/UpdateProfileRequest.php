<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.max' => 'Die Telefonnummer darf maximal 20 Zeichen haben.',
            'address.max' => 'Die Adresse darf maximal 255 Zeichen haben.',
            'city.max' => 'Die Stadt darf maximal 100 Zeichen haben.',
            'postal_code.max' => 'Die Postleitzahl darf maximal 10 Zeichen haben.',
        ];
    }
}
