<?php

namespace App\Http\Requests;

use App\Models\Addon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller via the AddonPolicy.
        return true;
    }

    /**
     * Fall back to a one-off additional service so that clients which predate
     * the usage/recurring fields keep working unchanged.
     */
    protected function prepareForValidation(): void
    {
        $billingType = $this->input('billing_type') ?: Addon::BILLING_TYPE_ONE_TIME;

        $this->merge([
            'service_type' => $this->input('service_type') ?: Addon::SERVICE_TYPE_ADDITIONAL,
            'billing_type' => $billingType,
            // A weekly display only makes sense for monthly billing, so one-off
            // add-ons always fall back to the monthly price.
            'price_display' => $billingType === Addon::BILLING_TYPE_RECURRING
                ? ($this->input('price_display') ?: Addon::PRICE_DISPLAY_MONTHLY)
                : Addon::PRICE_DISPLAY_MONTHLY,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:9999.99',
            // Display-only choice; the weekly figure is derived, never stored.
            'price_display' => ['sometimes', Rule::in(Addon::PRICE_DISPLAYS)],
            'payment_method' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',

            'service_type' => ['sometimes', Rule::in(Addon::SERVICE_TYPES)],
            'billing_type' => ['sometimes', Rule::in(Addon::BILLING_TYPES)],

            // The trial only makes sense for recurring billing.
            'trial_rest_of_month' => 'boolean',

            // Usage fields are required for usage services and ignored otherwise.
            'usage_period' => [
                Rule::requiredIf($this->isUsageService()),
                'nullable',
                Rule::in(Addon::USAGE_PERIODS),
            ],
            'usage_duration' => [
                Rule::requiredIf($this->hasFixedUsagePeriod()),
                'nullable',
                'integer',
                'min:1',
                'max:1000',
            ],
            'usage_duration_unit' => [
                Rule::requiredIf($this->hasFixedUsagePeriod()),
                'nullable',
                Rule::in(Addon::DURATION_UNITS),
            ],
            // A null quota amount means an unlimited flat rate.
            'quota_amount' => 'nullable|integer|min:1|max:100000',
            'quota_interval' => [
                Rule::requiredIf(fn () => $this->isUsageService() && $this->filled('quota_amount')),
                'nullable',
                Rule::in(Addon::QUOTA_INTERVALS),
            ],
            'settled_via_device' => 'boolean',

            // Per-plan assignment: keyed by membership_plan_id, value is the mode.
            'plan_modes' => 'nullable|array',
            'plan_modes.*' => ['nullable', Rule::in(['included', 'optional'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Bitte gib einen Namen für das Add-on an.',
            'price.required' => 'Bitte gib einen Preis an.',
            'price.numeric' => 'Der Preis muss eine Zahl sein.',
            'service_type.required' => 'Bitte wähle einen Leistungstyp aus.',
            'billing_type.required' => 'Bitte wähle eine Abrechnungsart aus.',
            'usage_period.required' => 'Bitte wähle einen Nutzungszeitraum aus.',
            'usage_duration.required' => 'Bitte gib die Dauer je Nutzung an.',
            'usage_duration_unit.required' => 'Bitte wähle eine Einheit für die Dauer aus.',
            'quota_interval.required' => 'Bitte wähle einen Zeitraum für das Kontingent aus.',
        ];
    }

    private function isUsageService(): bool
    {
        return $this->input('service_type') === Addon::SERVICE_TYPE_USAGE;
    }

    private function hasFixedUsagePeriod(): bool
    {
        return $this->isUsageService()
            && $this->input('usage_period') === Addon::USAGE_PERIOD_FIXED;
    }
}
