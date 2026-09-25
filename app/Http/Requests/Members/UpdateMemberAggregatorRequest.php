<?php

namespace App\Http\Requests\Members;

use App\Enums\Aggregator;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberAggregatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('member'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->aggregator_account_id)) {
            $this->merge(['aggregator_account_id' => trim($this->aggregator_account_id)]);
        }
    }

    public function rules(): array
    {
        /** @var Member $member */
        $member = $this->route('member');

        return [
            'aggregator' => ['required', Rule::enum(Aggregator::class)],
            'aggregator_account_id' => [
                'required',
                'string',
                'max:100',
                // Printable characters only, the id is shown and later matched
                // against the aggregator's check-in data.
                'regex:/^[\pL\pN._:\/-]+$/u',
                function (string $attribute, mixed $value, Closure $fail) use ($member) {
                    // One aggregator account belongs to one member per gym.
                    $taken = MemberAccessConfig::query()
                        ->where('aggregator', $this->input('aggregator'))
                        ->where('aggregator_account_id', $value)
                        ->where('member_id', '!=', $member->id)
                        ->whereHas('member', fn ($q) => $q->where('gym_id', $member->gym_id))
                        ->exists();

                    if ($taken) {
                        $fail('Diese Account-ID ist bereits einem anderen Mitglied zugeordnet.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'aggregator.required' => 'Bitte wählen Sie einen Anbieter aus.',
            'aggregator.enum' => 'Der ausgewählte Anbieter ist ungültig.',
            'aggregator_account_id.required' => 'Bitte geben Sie die Account-ID des Anbieters ein.',
            'aggregator_account_id.max' => 'Die Account-ID darf höchstens 100 Zeichen lang sein.',
            'aggregator_account_id.regex' => 'Die Account-ID enthält ungültige Zeichen.',
        ];
    }

    public function aggregator(): Aggregator
    {
        return Aggregator::from($this->validated('aggregator'));
    }
}
