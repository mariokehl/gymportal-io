<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoogleSheetSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled in the controller via the GymPolicy.
        return true;
    }

    /**
     * Normalise the multipart "enabled" flag into a real boolean before
     * validation runs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
        ]);
    }

    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
            // JSON service account key. Limited to 64 KB; a real key is < 10 KB.
            'credentials_file' => 'nullable|file|mimetypes:application/json,text/plain|max:64',
            'sheet_url' => 'required_if:enabled,true|nullable|url|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'sheet_url.required_if' => 'Bitte gib die URL des Google Sheets an.',
            'sheet_url.url' => 'Bitte gib eine gültige Google-Sheets-URL an.',
            'credentials_file.mimetypes' => 'Der Service-Account-Schlüssel muss eine JSON-Datei sein.',
            'credentials_file.max' => 'Die JSON-Datei ist zu groß.',
        ];
    }
}
