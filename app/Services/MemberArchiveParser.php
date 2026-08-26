<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use RuntimeException;
use ZipArchive;

/**
 * Parses member archives exported from a third-party gym management system.
 *
 * An archive is a set of per-member folders. Each folder is named
 * "<memberNumber>_<name>_<note>" and contains a mix of JSON and XLSX files.
 * Only "master_data.xlsx" is mandatory; every other file enriches the result.
 *
 * The parser is intentionally free of any database access so it can be unit
 * tested against fixture folders and reused for the ZIP and folder upload path.
 */
class MemberArchiveParser
{
    /**
     * Files that are read when present. Everything else in a folder is ignored.
     */
    private const KNOWN_FILES = [
        'master_data.xlsx',
        'access_identifications.xlsx',
        'account_data.xlsx',
        'benefit.xlsx',
        'customer.json',
        'customer_extended.json',
        'contact.json',
        'contracts.json',
        'bank_accounts.json',
        'liable_person.json',
    ];

    /**
     * Extract a ZIP archive into a temporary directory and return the path.
     *
     * The caller is responsible for removing the directory again.
     */
    public function extractZip(string $zipPath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Das ZIP-Archiv konnte nicht geöffnet werden.');
        }

        $target = storage_path('app/tmp/member-archive-'.bin2hex(random_bytes(8)));

        if (! mkdir($target, 0700, true) && ! is_dir($target)) {
            throw new RuntimeException('Temporäres Verzeichnis konnte nicht angelegt werden.');
        }

        // Extract entry by entry so path traversal ("../") and absolute paths
        // from a manipulated archive can never escape the target directory.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }

            $relative = $this->sanitizeArchivePath($name);

            if ($relative === null) {
                continue;
            }

            // Only the files we actually read are extracted at all.
            if (! in_array(basename($relative), self::KNOWN_FILES, true)) {
                continue;
            }

            $destination = $target.'/'.$relative;
            $directory = dirname($destination);

            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                continue;
            }

            $stream = $zip->getStream($name);

            if ($stream === false) {
                continue;
            }

            file_put_contents($destination, $stream);
            fclose($stream);
        }

        $zip->close();

        return $target;
    }

    /**
     * Normalise an archive entry to a safe relative path, or null if unsafe.
     */
    private function sanitizeArchivePath(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);

        // Skip macOS resource forks and absolute paths.
        if (str_starts_with($name, '__MACOSX/') || str_starts_with($name, '/')) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    /**
     * Locate all member folders below a directory.
     *
     * A member folder is any directory that contains a master_data.xlsx. This
     * works for a single uploaded folder as well as for an extracted ZIP that
     * wraps everything in one parent directory.
     *
     * @return array<int, string> absolute folder paths
     */
    public function findMemberFolders(string $root): array
    {
        $folders = [];

        if (is_file($root.'/master_data.xlsx')) {
            $folders[] = $root;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir() && is_file($entry->getPathname().'/master_data.xlsx')) {
                $folders[] = $entry->getPathname();
            }
        }

        sort($folders);

        return array_values(array_unique($folders));
    }

    /**
     * Parse a single member folder into a normalised array.
     */
    public function parseMemberFolder(string $folder): array
    {
        $master = $this->readMasterData($folder.'/master_data.xlsx');

        if ($master === null) {
            throw new RuntimeException('master_data.xlsx konnte nicht gelesen werden.');
        }

        $customer = $this->readJson($folder.'/customer.json') ?? [];
        $extended = $this->readJson($folder.'/customer_extended.json') ?? [];
        $contact = $this->readJson($folder.'/contact.json') ?? [];
        $contracts = $this->readJson($folder.'/contracts.json') ?? [];
        $bankAccounts = $this->readJson($folder.'/bank_accounts.json') ?? [];
        $liablePerson = $this->readJson($folder.'/liable_person.json');

        $primary = $master['primary'];

        return [
            'source_folder' => basename($folder),
            'member' => $this->buildMember($primary, $customer, $extended, $contact),
            'contract' => $this->buildContract($primary, $contracts),
            'modules' => $this->buildModules($master['modules'], $contracts),
            'bank_account' => $this->buildBankAccount($primary, $bankAccounts),
            'balance' => $this->readAccountBalance($folder.'/account_data.xlsx', $primary),
            'access_tags' => $this->readAccessIdentifications($folder.'/access_identifications.xlsx', $primary),
            'legal_guardian' => $this->buildLegalGuardian($liablePerson),
            'paid_until' => $this->parseGermanDate($primary['Bezahlt bis'] ?? null),
            'archived' => ($primary['archiviert'] ?? 'Nein') === 'Ja',
        ];
    }

    /**
     * Build the member master data from the flat sheet plus the JSON details.
     */
    private function buildMember(array $primary, array $customer, array $extended, array $contact): array
    {
        $address = $customer['addresses'][0] ?? $extended['addresses'][0] ?? [];

        $street = trim(($address['street'] ?? '').' '.($address['houseNumber'] ?? ''));

        if ($street === '') {
            $street = trim($primary['Straße'] ?? '');
        }

        $phone = $contact['telPrivateMobile']
            ?? $contact['telPrivate']
            ?? $contact['telBusinessMobile']
            ?? $contact['telBusiness']
            ?? (($primary['Mobil (privat)'] ?? '') ?: null)
            ?? (($primary['Telefon (privat)'] ?? '') ?: null);

        return [
            'member_number' => trim($primary['Mitgliedsnummer'] ?? ''),
            'salutation' => $this->mapSalutation($primary['Anrede'] ?? null),
            'first_name' => $customer['firstName'] ?? $primary['Vorname'] ?? '',
            'last_name' => $customer['lastName'] ?? $primary['Nachname'] ?? '',
            'email' => trim($customer['email'] ?? $contact['email'] ?? $primary['E-Mail'] ?? ''),
            'phone' => $phone ? trim($phone) : null,
            'birth_date' => $this->normaliseDate($customer['dateOfBirth'] ?? null)
                ?? $this->parseGermanDate($primary['Geburtstag'] ?? null),
            'address' => $street ?: null,
            'address_addition' => trim($address['addition'] ?? $primary['Adresszusatz'] ?? '') ?: null,
            'postal_code' => trim($address['zip'] ?? $primary['PLZ'] ?? '') ?: null,
            'city' => trim($address['city'] ?? $primary['Ort'] ?? '') ?: null,
            'country' => strtoupper(trim($address['country'] ?? $primary['Land'] ?? '')) ?: 'DE',
            'notes' => $this->buildNotes($primary),
        ];
    }

    /**
     * The sheet holds up to two note columns, both labelled "Notizen".
     */
    private function buildNotes(array $primary): ?string
    {
        $notes = array_filter([
            trim($primary['Notizen'] ?? ''),
            trim($primary['Notizen_2'] ?? ''),
        ]);

        return $notes === [] ? null : implode("\n", $notes);
    }

    /**
     * Build the main contract from the primary sheet row, enriched by the JSON
     * export which carries the exact term and extension values.
     */
    private function buildContract(array $primary, array $contracts): array
    {
        $json = $this->findContractByRate($contracts, $primary['Tarifname'] ?? '', ['CONTRACT']);

        return [
            'plan_name' => trim($json['rateName'] ?? $primary['Tarifname'] ?? ''),
            'price' => $this->parsePrice($primary['Preis'] ?? null)
                ?? ($json['chargeAmountCurrent'] ?? null),
            'billing_cycle' => $this->mapBillingCycle($json['paymentFrequency'] ?? $primary['Zahlweise'] ?? null),
            'commitment_months' => $this->parsePeriodInMonths($json['rateTerm'] ?? $primary['Laufzeit'] ?? null),
            'cancellation_period' => $this->parsePeriod($json['cancellationPeriod'] ?? $primary['Kündigungsfrist'] ?? null),
            'renewal_months' => $this->parsePeriodInMonths($json['extensionValue'] ?? $primary['Verlängerungsdauer'] ?? null),
            'start_date' => $this->normaliseDate($json['startDate'] ?? null)
                ?? $this->parseGermanDate($primary['Vertragsbeginn'] ?? null),
            'end_date' => $this->normaliseDate($json['endDate'] ?? null)
                ?? $this->parseGermanDate($primary['Vertragsende'] ?? null),
            'cancelled_to' => $this->normaliseDate($json['cancelledTo'] ?? null)
                ?? $this->parseGermanDate($primary['Gekündigt zum'] ?? null),
            'cancelled_at' => $this->normaliseDate($json['cancelledAt'] ?? null)
                ?? $this->parseGermanDate($primary['Gekündigt am'] ?? null),
            'cancellation_reason' => trim($json['cancellationReason'] ?? '') ?: null,
            'setup_fee' => $this->findFlatFeeAmount($contracts),
            'payment_type' => trim($json['paymentType'] ?? $primary['Zahlungsmethode'] ?? ''),
        ];
    }

    /**
     * Module contracts are add-on products booked on top of the main contract,
     * e.g. a drinks flat rate. Cancelled modules are skipped.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildModules(array $moduleRows, array $contracts): array
    {
        $modules = [];

        foreach ($moduleRows as $row) {
            $name = trim($row['Tarifname'] ?? '');

            if ($name === '') {
                continue;
            }

            $cancelledTo = $this->parseGermanDate($row['Gekündigt zum'] ?? null);

            // A module that already ended brings no future charge, so it is not
            // carried over into the new system.
            if ($cancelledTo !== null && Carbon::parse($cancelledTo)->isPast()) {
                continue;
            }

            $json = $this->findContractByRate($contracts, $name, ['MODULE_CONTRACT']);

            $modules[] = [
                'name' => $name,
                'price' => $this->parsePrice($row['Preis'] ?? null) ?? ($json['chargeAmountCurrent'] ?? null),
                'billing_cycle' => $this->mapBillingCycle($json['paymentFrequency'] ?? $row['Zahlweise'] ?? null),
                'start_date' => $this->normaliseDate($json['startDate'] ?? null)
                    ?? $this->parseGermanDate($row['Vertragsbeginn'] ?? null),
                'end_date' => $this->normaliseDate($json['endDate'] ?? null)
                    ?? $this->parseGermanDate($row['Vertragsende'] ?? null),
                'cancelled_to' => $cancelledTo,
            ];
        }

        return $modules;
    }

    /**
     * Bank details plus the existing SEPA mandate, so the mandate does not have
     * to be collected from the member again after the migration.
     */
    private function buildBankAccount(array $primary, array $bankAccounts): ?array
    {
        $account = null;

        // Prefer an account without an end date, i.e. the one currently in use.
        foreach ($bankAccounts as $candidate) {
            if (empty($candidate['endDate'])) {
                $account = $candidate;
                break;
            }
        }

        $account ??= $bankAccounts[0] ?? [];

        $iban = preg_replace('/\s+/', '', (string) ($account['iban'] ?? $primary['IBAN'] ?? ''));

        if ($iban === '') {
            return null;
        }

        $mandate = null;

        foreach ($account['sepaMandateDtos'] ?? [] as $candidate) {
            if (empty($candidate['mandateWithdrawnDate'])) {
                $mandate = $candidate;
                break;
            }
        }

        return [
            'iban' => strtoupper($iban),
            'bic' => trim($account['bic'] ?? $primary['BIC'] ?? '') ?: null,
            'bank_name' => trim($account['bankName'] ?? $primary['Bankname'] ?? '') ?: null,
            'account_holder' => trim($account['accountHolder'] ?? $primary['Kontoinhaber'] ?? '') ?: null,
            'mandate_reference' => trim($mandate['referenceNumber'] ?? $primary['SEPA-Mandatsreferenz-Nr.'] ?? '') ?: null,
            'mandate_signed_at' => $this->normaliseDate($mandate['mandateGivenDate'] ?? null),
            'mandate_confirmed' => ($mandate['sepaMandateStatus'] ?? null) === 'CONFIRMED',
        ];
    }

    /**
     * Legal guardian for underage members.
     */
    private function buildLegalGuardian(?array $liablePerson): ?array
    {
        if (! $liablePerson || empty($liablePerson['lastName'])) {
            return null;
        }

        return [
            'first_name' => trim($liablePerson['firstName'] ?? '') ?: null,
            'last_name' => trim($liablePerson['lastName']),
        ];
    }

    /**
     * Account balance and consumption credit from the account sheet header.
     */
    private function readAccountBalance(string $path, array $primary): array
    {
        $balance = $this->parsePrice($primary['Saldo'] ?? null) ?? 0.0;
        $credit = $this->parsePrice($primary['Verzehrguthabenausgleich'] ?? null) ?? 0.0;

        foreach ($this->readSheet($path) as $row) {
            $label = trim($row[0] ?? '');

            if ($label === 'Kontostand:' && isset($row[1])) {
                $balance = (float) $row[1];
            }

            if ($label === 'Verzehrguthaben:' && isset($row[1])) {
                $credit = (float) $row[1];
            }
        }

        return ['balance' => $balance, 'credit' => $credit];
    }

    /**
     * Access identifications: member card (NFC) and QR code identifier.
     */
    private function readAccessIdentifications(string $path, array $primary): array
    {
        $tags = ['nfc_uid' => null, 'qr_code' => null];

        foreach ($this->readSheet($path) as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $type = trim($row[0] ?? '');
            $value = trim($row[1] ?? '');

            if ($value === '') {
                continue;
            }

            if (str_contains($type, 'karte')) {
                $tags['nfc_uid'] = $value;
            } elseif (str_contains($type, 'QR')) {
                $tags['qr_code'] = $value;
            }
        }

        $tags['nfc_uid'] ??= trim($primary['Mitgliedskarte'] ?? '') ?: null;

        return $tags;
    }

    /**
     * Read the master sheet: the first data row is the main contract, every
     * further row is a module contract of the same member.
     *
     * @return array{primary: array<string, string>, modules: array<int, array<string, string>>}|null
     */
    private function readMasterData(string $path): ?array
    {
        $rows = $this->readSheet($path);

        if (count($rows) < 2) {
            return null;
        }

        $header = $this->uniqueHeader(array_shift($rows));
        $primary = null;
        $modules = [];

        foreach ($rows as $row) {
            $assoc = $this->combineRow($header, $row);

            if (trim($assoc['Mitgliedsnummer'] ?? '') === '') {
                continue;
            }

            // "Vertrag" marks the main contract, "Modulvertrag" an add-on.
            if ($primary === null && ($assoc['Typ'] ?? '') !== 'Modulvertrag') {
                $primary = $assoc;

                continue;
            }

            if (($assoc['Typ'] ?? '') === 'Modulvertrag') {
                $modules[] = $assoc;
            }
        }

        if ($primary === null) {
            return null;
        }

        return ['primary' => $primary, 'modules' => $modules];
    }

    /**
     * The sheet repeats some column labels; suffix duplicates to keep them.
     *
     * @param  array<int, string>  $header
     * @return array<int, string>
     */
    private function uniqueHeader(array $header): array
    {
        $seen = [];

        foreach ($header as $index => $label) {
            $label = trim($label);
            $seen[$label] = ($seen[$label] ?? 0) + 1;

            $header[$index] = $seen[$label] > 1 ? $label.'_'.$seen[$label] : $label;
        }

        return $header;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string>  $row
     * @return array<string, string>
     */
    private function combineRow(array $header, array $row): array
    {
        $row = array_pad(array_slice($row, 0, count($header)), count($header), '');

        return array_combine($header, $row);
    }

    /**
     * Find the contract entry matching a rate name and one of the given types.
     */
    private function findContractByRate(array $contracts, string $rateName, array $types): array
    {
        $rateName = trim($rateName);

        foreach ($contracts as $contract) {
            if (! in_array($contract['type'] ?? '', $types, true)) {
                continue;
            }

            if (trim($contract['rateName'] ?? '') !== $rateName) {
                continue;
            }

            // Prefer a contract that is still running over a terminated one.
            if (empty($contract['cancelledTo'])) {
                return $contract;
            }
        }

        foreach ($contracts as $contract) {
            if (in_array($contract['type'] ?? '', $types, true) && trim($contract['rateName'] ?? '') === $rateName) {
                return $contract;
            }
        }

        return [];
    }

    /**
     * A one-off activation fee is exported as a separate flat fee contract.
     */
    private function findFlatFeeAmount(array $contracts): ?float
    {
        foreach ($contracts as $contract) {
            if (($contract['type'] ?? '') === 'FLAT_FEE_CONTRACT') {
                return $contract['chargeAmountCurrent'] ?? null;
            }
        }

        return null;
    }

    /**
     * Read an XLSX sheet without an external dependency.
     *
     * @return array<int, array<int, string>>
     */
    public function readSheet(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return [];
        }

        $shared = $this->readSharedStrings($zip);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');

        $zip->close();

        if ($sheet === false) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($sheet);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $index = $this->columnIndex((string) $cell['r']);
                $values[$index] = $this->cellValue($cell, $shared);
            }

            if ($values === []) {
                $rows[] = [];

                continue;
            }

            // Fill gaps so column positions stay aligned with the header.
            $normalised = [];

            for ($i = 0; $i <= max(array_keys($values)); $i++) {
                $normalised[$i] = $values[$i] ?? '';
            }

            $rows[] = $normalised;
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');

        if ($content === false) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $item) {
            $text = '';

            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                $text .= (string) $node;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $shared
     */
    private function cellValue(\SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $text = '';

            foreach ($cell->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                $text .= (string) $node;
            }

            return $text;
        }

        $value = (string) $cell->v;

        if ($type === 's') {
            return $shared[(int) $value] ?? '';
        }

        return $value;
    }

    /**
     * Convert a cell reference such as "AB12" into a zero-based column index.
     */
    private function columnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)/', $reference, $matches);

        $letters = $matches[1] ?? 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * Read and decode a JSON file, unwrapping the common DTO envelopes.
     */
    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    private function mapSalutation(?string $value): ?string
    {
        return match (trim((string) $value)) {
            'Herr' => 'Herr',
            'Frau' => 'Frau',
            default => null,
        };
    }

    /**
     * Map an export payment frequency ("1M", "3M", "12M") or its German label
     * onto a gymportal billing cycle.
     */
    private function mapBillingCycle(?string $value): string
    {
        $value = trim((string) $value);

        return match ($value) {
            '3M', 'vierteljährlich' => 'quarterly',
            '6M', 'halbjährlich' => 'biannual',
            '12M', 'jährlich' => 'yearly',
            default => 'monthly',
        };
    }

    /**
     * Parse a period such as "4M", "2W" or "14D" into whole months.
     */
    private function parsePeriodInMonths(?string $value): int
    {
        $period = $this->parsePeriod($value);

        if ($period === null) {
            return 0;
        }

        return match ($period['unit']) {
            'months' => $period['value'],
            'weeks' => (int) round($period['value'] / 4),
            default => (int) round($period['value'] / 30),
        };
    }

    /**
     * Parse a period such as "4M", "2W" or "14D".
     *
     * @return array{value: int, unit: string}|null
     */
    private function parsePeriod(?string $value): ?array
    {
        if (! preg_match('/^(\d+)\s*([MWDJ])$/i', trim((string) $value), $matches)) {
            return null;
        }

        $unit = match (strtoupper($matches[2])) {
            'W' => 'weeks',
            'D' => 'days',
            default => 'months',
        };

        return ['value' => (int) $matches[1], 'unit' => $unit];
    }

    /**
     * Parse a price cell such as "monatlich: 35,98 €" or "0,00".
     */
    private function parsePrice(?string $value): ?float
    {
        $value = (string) $value;

        // Strip the leading interval label and any non-breaking spaces.
        $value = str_replace(["\u{00A0}", '€'], ['', ''], $value);

        if (! preg_match('/(-?[\d.]*\d(?:,\d+)?)\s*$/', trim($value), $matches)) {
            return null;
        }

        return (float) str_replace([',', ' '], ['.', ''], str_replace('.', '', $matches[1]));
    }

    /**
     * Normalise an ISO date (possibly with a time part) to Y-m-d.
     */
    private function normaliseDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Parse a German date such as "15.01.1980".
     */
    private function parseGermanDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d.m.Y', $value)->toDateString();
        } catch (\Exception) {
            return $this->normaliseDate($value);
        }
    }
}
