<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Smalot\PdfParser\Parser;

class ImportPdfContracts extends Command
{
    protected $signature = 'pdf:import-contracts
                            {directory : Verzeichnis mit den PDF-Vertragsdateien}
                            {--output=contracts.csv : Pfad zur Ausgabe-CSV-Datei}';

    protected $description = 'Liest Vertrags-PDFs aus einem Verzeichnis ein und exportiert die Daten als CSV';

    public function handle(): int
    {
        $directory = $this->argument('directory');
        $outputPath = $this->option('output');

        if (!is_dir($directory)) {
            $this->error("Verzeichnis nicht gefunden: {$directory}");
            return 1;
        }

        $pdfFiles = glob(rtrim($directory, '/') . '/*.pdf');

        if (empty($pdfFiles)) {
            $this->warn("Keine PDF-Dateien in {$directory} gefunden.");
            return 0;
        }

        $this->info("Gefunden: " . count($pdfFiles) . " PDF-Datei(en)");

        $parser = new Parser();
        $rows = [];
        $errors = 0;

        foreach ($pdfFiles as $pdfFile) {
            $filename = basename($pdfFile);

            try {
                $pdf = $parser->parseFile($pdfFile);
                $text = $pdf->getText();
                $data = $this->extract($text);
                $rows[] = $this->flattenRow($data, $filename);
                $this->info("  OK: {$filename}");
            } catch (\Exception $e) {
                $errors++;
                $this->warn("  Fehler bei {$filename}: {$e->getMessage()}");
            }
        }

        if (empty($rows)) {
            $this->error("Keine Daten extrahiert.");
            return 1;
        }

        $this->writeCsv($outputPath, $rows);

        $this->info("\n===========================================");
        $this->info("Zusammenfassung");
        $this->info("===========================================");
        $this->info("Verarbeitet: " . count($rows) . " / " . count($pdfFiles));
        if ($errors > 0) {
            $this->warn("Fehler: {$errors}");
        }
        $this->info("CSV geschrieben: {$outputPath}");
        $this->info("===========================================");

        return 0;
    }

    private function extract(string $text): array
    {
        return [
            'vertragsart' => 'Neuanmeldung',
            'tarif' => $this->extractAfterLabel($text, 'Mitgliedschaft'),
            'monatsbeitrag' => $this->extractPrice($text),
            'email' => $this->extractAfterLabel($text, 'E-Mail'),
            'anrede' => $this->extractAfterLabel($text, 'Anrede'),
            'name' => $this->extractAfterLabel($text, 'Name'),
            'geburtsdatum' => $this->parseDate($this->extractAfterLabel($text, 'Geburtsdatum')),
            'adresse' => $this->extractAddress($text),
            'telefon' => $this->extractAfterLabel($text, 'Telefon'),
            'kontoinhaber' => $this->extractAfterLabel($text, 'Kontoinhaber'),
            'iban' => $this->normalizeIban($this->extractAfterLabel($text, 'IBAN')),
            'vertragsdatum' => $this->parseDate($this->extractAfterLabel($text, 'Datum')),
            'widerrufsrecht_tage' => 14,
            'kuendigungsfrist' => '1 Monat zum Monatsende',
        ];
    }

    private function flattenRow(array $data, string $filename): array
    {
        $adresse = $data['adresse'] ?? [];

        return [
            'datei' => $filename,
            'vertragsart' => $data['vertragsart'],
            'tarif' => $data['tarif'],
            'monatsbeitrag' => $data['monatsbeitrag'],
            'email' => $data['email'],
            'anrede' => $data['anrede'],
            'name' => $data['name'],
            'geburtsdatum' => $data['geburtsdatum'],
            'adresse_strasse' => $adresse['strasse'] ?? null,
            'adresse_hausnummer' => $adresse['hausnummer'] ?? null,
            'adresse_ort' => $adresse['ort'] ?? null,
            'adresse_plz' => $adresse['plz'] ?? null,
            'telefon' => $data['telefon'],
            'kontoinhaber' => $data['kontoinhaber'],
            'iban' => $data['iban'],
            'vertragsdatum' => $data['vertragsdatum'],
            'widerrufsrecht_tage' => $data['widerrufsrecht_tage'],
            'kuendigungsfrist' => $data['kuendigungsfrist'],
        ];
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

        fputcsv($handle, array_keys($rows[0]), ';');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        fclose($handle);
    }

    private function extractAfterLabel(string $text, string $label): ?string
    {
        $pattern = '/' . preg_quote($label, '/') . '\s*\n([^\n]+)/i';
        preg_match($pattern, $text, $matches);

        return isset($matches[1]) ? trim($matches[1]) : null;
    }

    private function extractPrice(string $text): ?string
    {
        preg_match('/(\d+)[.,](\d{2})\s*[€*]/i', $text, $matches);

        return isset($matches[1]) ? "{$matches[1]}.{$matches[2]}" : null;
    }

    private function extractAddress(string $text): ?array
    {
        // Format 1: Straße\nHausnummer\nPLZ Ort
        if (preg_match('/Anschrift\s*\n([^\n]+)\n(\d+[\s]*[a-zA-Z]?(?:[\/\-]\d+)?)\n(\d{5})\s+([^\n]+)/i', $text, $matches)) {
            return [
                'strasse' => trim($matches[1]),
                'hausnummer' => trim($matches[2]),
                'plz' => trim($matches[3]),
                'ort' => trim($matches[4]),
            ];
        }

        // Format 2: Straße\nHausnummer\nOrt PLZ
        if (preg_match('/Anschrift\s*\n([^\n]+)\n(\d+[\s]*[a-zA-Z]?(?:[\/\-]\d+)?)\n([^\n]+)\s+(\d{5})/i', $text, $matches)) {
            return [
                'strasse' => trim($matches[1]),
                'hausnummer' => trim($matches[2]),
                'ort' => trim($matches[3]),
                'plz' => trim($matches[4]),
            ];
        }

        return null;
    }

    private function normalizeIban(?string $iban): ?string
    {
        if (!$iban) {
            return null;
        }

        return strtoupper(preg_replace('/\s/', '', $iban));
    }

    private function parseDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        $date = preg_replace('/\s+\d+:\d+:\d+/', '', $date);

        try {
            $parsed = Carbon::createFromFormat('d.m.Y', $date)
                ?? Carbon::createFromFormat('d-m-Y', $date)
                ?? Carbon::createFromFormat('j-n-Y', $date);

            return $parsed?->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
