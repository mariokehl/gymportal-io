<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A walk-in customer creating a guest account in the guest app.
 */
class GuestRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|min:2|max:255',
            'last_name' => 'required|string|min:2|max:255',
            'birth_date' => 'required|date|before:today',
            'email' => 'required|email|max:255',
            'gym_slug' => 'required|string|max:255',
        ];
    }
}
