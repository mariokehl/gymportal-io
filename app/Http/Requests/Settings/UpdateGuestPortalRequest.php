<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuestPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->user()->currentGym);
    }

    public function rules(): array
    {
        return [
            'guest_enabled' => 'required|boolean',
        ];
    }
}
