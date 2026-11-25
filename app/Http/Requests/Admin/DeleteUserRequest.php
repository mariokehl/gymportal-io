<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('user'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The admin has to retype the e-mail address to confirm the deletion
            'confirmation' => ['required', 'string', Rule::in([$this->route('user')->email])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => 'Bitte geben Sie zur Bestätigung die E-Mail-Adresse des Benutzers ein.',
            'confirmation.in' => 'Die E-Mail-Adresse stimmt nicht überein.',
        ];
    }
}
