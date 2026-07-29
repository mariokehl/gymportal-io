<?php

namespace App\Http\Requests\Settings;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentExecutionSettingsRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller against the current gym,
     * which is resolved from the authenticated user.
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
        $min = PaymentMethod::MIN_EXECUTION_OFFSET;
        $max = PaymentMethod::MAX_EXECUTION_OFFSET;

        return [
            'method' => ['required', 'string', 'max:64'],
            // A null offset resets the payment method back to the system default.
            'initial' => ['nullable', 'integer', "min:{$min}", "max:{$max}"],
            'recurring' => ['nullable', 'integer', "min:{$min}", "max:{$max}"],
        ];
    }

    public function messages(): array
    {
        return [
            'initial.min' => 'Die Verschiebung der initialen Zahlung liegt außerhalb des erlaubten Bereichs.',
            'initial.max' => 'Die Verschiebung der initialen Zahlung liegt außerhalb des erlaubten Bereichs.',
            'recurring.min' => 'Die Verschiebung der wiederkehrenden Zahlung liegt außerhalb des erlaubten Bereichs.',
            'recurring.max' => 'Die Verschiebung der wiederkehrenden Zahlung liegt außerhalb des erlaubten Bereichs.',
        ];
    }
}
