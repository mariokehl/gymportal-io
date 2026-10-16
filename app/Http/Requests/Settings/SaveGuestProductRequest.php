<?php

namespace App\Http\Requests\Settings;

use App\Models\GuestProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update a product of the guest shop. The type cannot change once
 * a product exists, since past purchases refer to what it granted.
 */
class SaveGuestProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('guestProduct');

        return $product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', GuestProduct::class);
    }

    public function rules(): array
    {
        $creating = $this->route('guestProduct') === null;

        return [
            'type' => [$creating ? 'required' : 'prohibited', Rule::in(GuestProduct::TYPES)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.5|max:9999.99',
            'quantity' => [
                Rule::requiredIf(fn () => $this->input('type', $this->route('guestProduct')?->type) !== GuestProduct::TYPE_DAY_PASS),
                'nullable',
                'integer',
                'min:1',
                'max:1000',
            ],
            'sort_order' => 'nullable|integer|min:0|max:999',
            'active' => 'boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'Art',
            'name' => 'Name',
            'price' => 'Preis',
            'quantity' => 'Menge',
        ];
    }
}
