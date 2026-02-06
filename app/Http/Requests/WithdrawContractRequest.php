<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validierung für Vertragswiderruf gemäß § 356a BGB
 *
 * Gemäß § 356a BGB dürfen NUR diese Angaben abgefragt werden:
 * - Name des Verbrauchers
 * - Angaben zur Identifizierung des Vertrags
 * - E-Mail für Eingangsbestätigung
 *
 * WICHTIG: Widerrufsgrund darf NICHT abgefragt werden!
 */
class WithdrawContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional - wird aus Profil geholt wenn leer
            'name' => 'nullable|string|max:255',

            // Erforderlich zur Identifizierung des Vertrags
            'membership_id' => 'required|integer|exists:memberships,id',

            // Optional - wird aus Profil geholt wenn leer
            'confirmation_email' => 'nullable|email|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'membership_id.required' => 'Die Mitgliedschafts-ID ist erforderlich.',
            'membership_id.integer' => 'Die Mitgliedschafts-ID muss eine Zahl sein.',
            'membership_id.exists' => 'Die angegebene Mitgliedschaft existiert nicht.',
            'confirmation_email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse an.',
        ];
    }
}
