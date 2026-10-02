<?php

namespace App\Http\Requests\Members;

use App\Services\NfcTagService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreMemberNfcTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('member'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->uid)) {
            $this->merge(['uid' => app(NfcTagService::class)->normalizeUid($this->uid) ?? $this->uid]);
        }
    }

    public function rules(): array
    {
        return [
            'uid' => [
                'required',
                'string',
                'max:255',
                'regex:/^[0-9A-F]+$/',
                function (string $attribute, mixed $value, Closure $fail) {
                    // Unique across primary and additional tags of all members,
                    // including the member's own primary tag.
                    if (app(NfcTagService::class)->isTaken($value)) {
                        $fail('Diese NFC-ID ist bereits einem Mitglied zugeordnet.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'uid.required' => 'Bitte geben Sie eine NFC-ID ein.',
            'uid.regex' => 'Die NFC-ID hat ein ungültiges Format.',
            'uid.max' => 'Die NFC-ID ist zu lang.',
        ];
    }
}
