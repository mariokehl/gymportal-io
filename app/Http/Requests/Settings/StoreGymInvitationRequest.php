<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGymInvitationRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller against the current gym via
     * the GymPolicy, which needs the resolved gym instance.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower((string) $this->input('email'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'indisposable', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'staff', 'trainer'])],
            // Optional — only used to pre-fill the account of a brand new invitee.
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
