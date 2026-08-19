<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A member scanning the printed station code with their phone.
 *
 * The gym is addressed by slug because that is what the printed URL carries;
 * the token proves the scan came from that gym's sheet. Neither identifies the
 * member — that comes from the authenticated session alone.
 */
class StationCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gym_slug' => 'required|string|max:255',

            // Length matches Str::random(48) from Gym::rotateCheckinStationToken().
            'station_token' => 'required|string|min:20|max:64',
        ];
    }

    public function messages(): array
    {
        return [
            'gym_slug.required' => 'Das Studio konnte nicht ermittelt werden.',
            'station_token.required' => 'Der gescannte Code ist unvollständig.',
        ];
    }
}
