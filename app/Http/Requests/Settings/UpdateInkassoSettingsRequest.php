<?php

namespace App\Http\Requests\Settings;

use App\Models\Gym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInkassoSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $gym = $this->user()?->currentGym;

        return $gym !== null && $this->user()->can('update', $gym);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'string', 'max:64'],
            // ClientDataItem.clientNumber has to be exactly five characters.
            'client_number' => ['nullable', 'string', 'size:5'],
            'username' => ['nullable', 'string', 'max:190'],
            // Only sent when it should be changed; an empty value keeps the stored one.
            'password' => ['nullable', 'string', 'max:190'],
            'creditor_name' => ['nullable', 'string', 'max:190'],
            'contact' => ['nullable', 'string', 'max:190'],
            'min_amount' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'include_minors' => ['required', 'boolean'],
            'residual_handling' => ['required', Rule::in([
                Gym::RESIDUAL_ALWAYS_WRITE_OFF,
                Gym::RESIDUAL_PARTNER_DECISION,
            ])],
            'auto_resubmit' => ['required', 'boolean'],
            'handover_flat_fee' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'default_interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'levels' => ['required', 'array', 'size:4'],
            'levels.*.level' => ['required', 'integer', 'between:1,4'],
            'levels.*.trigger_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'levels.*.fee' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'levels.*.effect' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_number.size' => 'Die Gläubigernummer muss genau 5 Zeichen lang sein.',
            'levels.size' => 'Es müssen genau 4 Mahnstufen konfiguriert sein.',
        ];
    }
}
