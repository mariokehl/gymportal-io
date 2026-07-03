<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheets;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService
{
    /**
     * Fixed header row of the target sheet. The service only fills the first
     * three data columns plus the email column; the remaining columns are
     * maintained manually by the external reviewer.
     */
    public const HEADER_COLUMNS = [
        'Name',
        'CheckIn Time',
        'Mitgliederart',
        'unauthorized occurrence? Type "x" for yes',
        'Email-Adresse',
        'EmailVersand?',
        'Case-ID',
        'Antwort erhalten',
        'Antwort erhalten Datum',
        'Für Tagesticket vorgeschlagen',
    ];

    /**
     * Range covering the header row / append target on the first sheet tab.
     */
    private const SHEET_RANGE = 'A1';

    /**
     * Required fields of a valid service account key.
     */
    private const REQUIRED_CREDENTIAL_KEYS = ['type', 'client_email', 'private_key'];

    /**
     * Validate that the given array is a well-formed service account key.
     */
    public function isValidServiceAccountKey(?array $credentials): bool
    {
        if (empty($credentials)) {
            return false;
        }

        foreach (self::REQUIRED_CREDENTIAL_KEYS as $key) {
            if (empty($credentials[$key])) {
                return false;
            }
        }

        return $credentials['type'] === 'service_account';
    }

    /**
     * Read the service account email (client_email) from the key.
     */
    public function serviceAccountEmailFrom(array $credentials): ?string
    {
        return $credentials['client_email'] ?? null;
    }

    /**
     * Extract the spreadsheet id from a full Google Sheets URL.
     */
    public function extractSpreadsheetId(string $url): ?string
    {
        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Build an authenticated Sheets service from per-gym credentials.
     */
    private function sheetsFor(array $credentials): GoogleSheets
    {
        $client = new GoogleClient;
        $client->setAuthConfig($credentials);
        $client->setScopes([GoogleSheets::SPREADSHEETS]);

        return new GoogleSheets($client);
    }

    /**
     * Verify that the service account can read the spreadsheet. Returns false
     * (and logs) when access is denied or the sheet does not exist, so the
     * caller can surface an actionable error at save time.
     */
    public function verifyAccess(array $credentials, string $spreadsheetId): bool
    {
        try {
            $this->sheetsFor($credentials)->spreadsheets->get($spreadsheetId);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Google Sheets access verification failed', [
                'spreadsheet_id' => $spreadsheetId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Write the header row once if the first sheet tab is still empty.
     */
    public function ensureHeaderRow(array $credentials, string $spreadsheetId): void
    {
        $sheets = $this->sheetsFor($credentials);

        $existing = $sheets->spreadsheets_values->get($spreadsheetId, self::SHEET_RANGE);

        if (! empty($existing->getValues())) {
            return;
        }

        $body = new ValueRange(['values' => [self::HEADER_COLUMNS]]);

        // RAW keeps the values verbatim so Google does not reinterpret them.
        $sheets->spreadsheets_values->update(
            $spreadsheetId,
            self::SHEET_RANGE,
            $body,
            ['valueInputOption' => 'RAW']
        );
    }

    /**
     * Append data rows to the first sheet tab.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    public function appendRows(array $credentials, string $spreadsheetId, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $body = new ValueRange(['values' => $rows]);

        // RAW prevents Google from turning "2026-07-02 09:30:00" into a serial
        // date number.
        $this->sheetsFor($credentials)->spreadsheets_values->append(
            $spreadsheetId,
            self::SHEET_RANGE,
            $body,
            [
                'valueInputOption' => 'RAW',
                'insertDataOption' => 'INSERT_ROWS',
            ]
        );
    }
}
