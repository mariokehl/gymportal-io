<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A guest buying a product. The payment method is only needed for the part
 * the guest's credit does not cover.
 */
class GuestCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer',
            'payment_method' => 'nullable|string|max:50',
        ];
    }
}
