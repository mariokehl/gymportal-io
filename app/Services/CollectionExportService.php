<?php

namespace App\Services;

use App\Models\CollectionRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports the detail file of a collection run, one row per claim.
 *
 * The project has no spreadsheet dependency, so the "Excel" variant is a
 * semicolon separated UTF-8 CSV with a BOM, which Excel opens natively with a
 * German locale.
 */
class CollectionExportService
{
    public const HEADERS = [
        'Lauf',
        'Aktenzeichen',
        'Fallnummer',
        'Mitgliedsnummer',
        'Nachname',
        'Vorname',
        'Straße',
        'PLZ',
        'Ort',
        'Land',
        'Geburtsdatum',
        'Forderungsart',
        'Beschreibung',
        'Fällig am',
        'Betrag',
        'Gezahlt',
        'Offen',
        'Fall-Status',
    ];

    /**
     * Build the export rows for a run.
     *
     * @return array<int, array<int, string>>
     */
    public function rows(CollectionRun $run): array
    {
        $rows = [];

        $cases = $run->cases()->with(['member', 'claims'])->get();

        foreach ($cases as $case) {
            foreach ($case->claims as $claim) {
                $rows[] = [
                    $run->run_number,
                    (string) ($case->partner_reference ?? ''),
                    $case->case_number,
                    (string) ($case->member->member_number ?? ''),
                    (string) ($case->member->last_name ?? ''),
                    (string) ($case->member->first_name ?? ''),
                    (string) ($case->member->address ?? ''),
                    (string) ($case->member->postal_code ?? ''),
                    (string) ($case->member->city ?? ''),
                    (string) ($case->member->country ?? ''),
                    $case->member?->birth_date ? $case->member->birth_date->format('d.m.Y') : '',
                    $claim->kind_label,
                    $claim->description,
                    $claim->due_date ? $claim->due_date->format('d.m.Y') : '',
                    $this->money($claim->amount),
                    $this->money($claim->paid_amount),
                    $this->money($claim->open_amount),
                    $case->status_text,
                ];
            }
        }

        return $rows;
    }

    /**
     * Stream the run as a downloadable file.
     */
    public function download(CollectionRun $run, string $format = 'csv'): StreamedResponse
    {
        $rows = $this->rows($run);
        $isExcel = $format === 'xlsx';
        $separator = $isExcel ? ';' : ',';
        $filename = $run->run_number.'.csv';

        return response()->streamDownload(function () use ($rows, $separator, $isExcel) {
            $handle = fopen('php://output', 'w');

            // Excel needs a BOM to detect UTF-8.
            if ($isExcel) {
                fwrite($handle, "\xEF\xBB\xBF");
            }

            fputcsv($handle, self::HEADERS, $separator);

            foreach ($rows as $row) {
                fputcsv($handle, $row, $separator);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.');
    }
}
