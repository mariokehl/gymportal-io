<?php

namespace Tests\Unit;

use App\Services\MemberArchiveParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class MemberArchiveParserTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/archive-parser-'.bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);

        parent::tearDown();
    }

    #[Test]
    public function it_parses_a_member_folder_with_a_module_contract(): void
    {
        $folder = $this->makeMemberFolder('9-101_Max Muster_Modul', [
            'master' => [
                'primary' => [
                    'Mitgliedsnummer' => '9-101',
                    'Anrede' => 'Herr',
                    'Vorname' => 'Max',
                    'Nachname' => 'Muster',
                    'Typ' => 'Vertrag',
                    'Tarifname' => 'Flex-Tarif',
                    'Preis' => "monatlich: 35,98\u{00A0}€",
                    'Bezahlt bis' => '30.06.2026',
                    'Saldo' => '0,00',
                ],
                'modules' => [
                    [
                        'Mitgliedsnummer' => '9-101',
                        'Typ' => 'Modulvertrag',
                        'Tarifname' => 'Getränke-Flatrate für Flex-Tarif',
                        'Preis' => "monatlich: 8,62\u{00A0}€",
                    ],
                ],
            ],
            'contracts' => [
                [
                    'rateName' => 'Flex-Tarif',
                    'type' => 'CONTRACT',
                    'rateTerm' => '4M',
                    'cancellationPeriod' => '3M',
                    'extensionValue' => '24M',
                    'paymentFrequency' => '1M',
                    'chargeAmountCurrent' => 35.98,
                    'startDate' => '2022-04-01',
                    'endDate' => '2028-07-31',
                    'paymentType' => 'Lastschrift',
                ],
                [
                    'rateName' => 'Getränke-Flatrate für Flex-Tarif',
                    'type' => 'MODULE_CONTRACT',
                    'paymentFrequency' => '1M',
                    'chargeAmountCurrent' => 8.62,
                    'startDate' => '2023-02-01',
                    'endDate' => '2026-07-31',
                ],
            ],
            'customer' => [
                'firstName' => 'Max',
                'lastName' => 'Muster',
                'dateOfBirth' => '1980-01-15',
                'email' => 'max@example.test',
                'addresses' => [[
                    'street' => 'Musterweg',
                    'houseNumber' => '15',
                    'zip' => '12345',
                    'city' => 'Musterstadt',
                    'country' => 'DE',
                ]],
            ],
        ]);

        $data = (new MemberArchiveParser)->parseMemberFolder($folder);

        $this->assertSame('Max', $data['member']['first_name']);
        $this->assertSame('Musterweg 15', $data['member']['address']);
        $this->assertSame('1980-01-15', $data['member']['birth_date']);

        $this->assertSame('Flex-Tarif', $data['contract']['plan_name']);
        $this->assertSame(35.98, $data['contract']['price']);
        $this->assertSame(4, $data['contract']['commitment_months']);
        $this->assertSame(['value' => 3, 'unit' => 'months'], $data['contract']['cancellation_period']);
        $this->assertSame(24, $data['contract']['renewal_months']);
        $this->assertSame('monthly', $data['contract']['billing_cycle']);

        $this->assertCount(1, $data['modules']);
        $this->assertSame('Getränke-Flatrate für Flex-Tarif', $data['modules'][0]['name']);
        $this->assertSame(8.62, $data['modules'][0]['price']);

        $this->assertSame('2026-06-30', $data['paid_until']);
    }

    #[Test]
    public function it_skips_module_contracts_that_already_ended(): void
    {
        $folder = $this->makeMemberFolder('1-1_Alt Mitglied_x', [
            'master' => [
                'primary' => [
                    'Mitgliedsnummer' => '1-1',
                    'Vorname' => 'Alt',
                    'Nachname' => 'Mitglied',
                    'Typ' => 'Vertrag',
                    'Tarifname' => 'Home-Tarif',
                    'Preis' => "monatlich: 39,95\u{00A0}€",
                ],
                'modules' => [
                    [
                        'Mitgliedsnummer' => '1-1',
                        'Typ' => 'Modulvertrag',
                        'Tarifname' => 'Getränke-Flatrate für Home-Tarif',
                        'Preis' => "monatlich: 8,62\u{00A0}€",
                        'Gekündigt zum' => '31.01.2023',
                    ],
                ],
            ],
        ]);

        $data = (new MemberArchiveParser)->parseMemberFolder($folder);

        $this->assertSame([], $data['modules']);
    }

    #[Test]
    public function it_reads_the_sepa_mandate_and_credit_balance(): void
    {
        $folder = $this->makeMemberFolder('9-102_Guthaben Test_x', [
            'master' => [
                'primary' => [
                    'Mitgliedsnummer' => '9-102',
                    'Vorname' => 'Guthaben',
                    'Nachname' => 'Test',
                    'Typ' => 'Vertrag',
                    'Tarifname' => 'Home-Tarif',
                    'Preis' => "monatlich: 39,95\u{00A0}€",
                ],
                'modules' => [],
            ],
            'bank_accounts' => [[
                'accountHolder' => 'Guthaben Test',
                'iban' => 'DE02500105170137075030',
                'bic' => 'BANKDEFFXXX',
                'bankName' => 'Testbank',
                'endDate' => null,
                'sepaMandateDtos' => [[
                    'sepaMandateStatus' => 'CONFIRMED',
                    'referenceNumber' => 'REF-102722',
                    'mandateGivenDate' => '2026-06-08',
                    'mandateWithdrawnDate' => null,
                ]],
            ]],
            'account_rows' => [
                ['Kontostand: ', '119.90'],
                ['Verzehrguthaben: ', '5.00'],
            ],
            'access' => [
                ['Typ', 'Kennung'],
                ['Mitgliedskarte', '1000000002'],
                ['Mitglieds-QR-Code', 'c0ffee00-0000-4000-8000-000000000000'],
            ],
            'liable_person' => [
                'firstName' => 'Erika',
                'lastName' => 'Test',
            ],
        ]);

        $data = (new MemberArchiveParser)->parseMemberFolder($folder);

        $this->assertSame('DE02500105170137075030', $data['bank_account']['iban']);
        $this->assertSame('REF-102722', $data['bank_account']['mandate_reference']);
        $this->assertSame('2026-06-08', $data['bank_account']['mandate_signed_at']);
        $this->assertTrue($data['bank_account']['mandate_confirmed']);

        $this->assertSame(119.90, $data['balance']['balance']);
        $this->assertSame(5.00, $data['balance']['credit']);

        $this->assertSame('1000000002', $data['access_tags']['nfc_uid']);
        $this->assertSame('c0ffee00-0000-4000-8000-000000000000', $data['access_tags']['qr_code']);

        $this->assertSame('Erika', $data['legal_guardian']['first_name']);
    }

    #[Test]
    public function it_finds_member_folders_inside_an_extracted_zip(): void
    {
        $this->makeMemberFolder('1-1_A B_x', ['master' => ['primary' => $this->minimalPrimary('1-1'), 'modules' => []]]);
        $this->makeMemberFolder('1-2_C D_x', ['master' => ['primary' => $this->minimalPrimary('1-2'), 'modules' => []]]);

        $folders = (new MemberArchiveParser)->findMemberFolders($this->root);

        $this->assertCount(2, $folders);
    }

    #[Test]
    public function it_ignores_path_traversal_entries_when_extracting_a_zip(): void
    {
        $zipPath = $this->root.'/archive.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('../../escaped/master_data.xlsx', 'x');
        $zip->addFromString('__MACOSX/master_data.xlsx', 'x');
        $zip->close();

        $parser = new MemberArchiveParser;
        $target = $parser->extractZip($zipPath);

        $this->assertFileDoesNotExist(dirname($target).'/../escaped/master_data.xlsx');
        $this->assertSame([], $parser->findMemberFolders($target));

        $this->removeDirectory($target);
    }

    /**
     * @return array<string, string>
     */
    private function minimalPrimary(string $number): array
    {
        return [
            'Mitgliedsnummer' => $number,
            'Vorname' => 'Test',
            'Nachname' => 'Person',
            'Typ' => 'Vertrag',
            'Tarifname' => 'Basis',
            'Preis' => "monatlich: 20,00\u{00A0}€",
        ];
    }

    /**
     * Build a member folder from an abstract description.
     */
    private function makeMemberFolder(string $name, array $spec): string
    {
        $folder = $this->root.'/'.$name;
        mkdir($folder, 0700, true);

        $columns = [
            'Mitgliedsnummer', 'Mitgliedskarte', 'Anrede', 'Titel', 'Geschlecht', 'Vorname', 'Nachname',
            'Straße', 'Adresszusatz', 'PLZ', 'Ort', 'Land', 'Geburtstag', 'Telefon (privat)',
            'E-Mail', 'Bankname', 'BIC', 'IBAN', 'Kontoinhaber', 'SEPA-Mandatsreferenz-Nr.', 'Notizen',
            'Typ', 'Tarifname', 'Laufzeit', 'Kündigungsfrist', 'Verlängerungsdauer', 'Vertragsbeginn',
            'Gekündigt zum', 'Gekündigt am', 'Vertragsende', 'Zahlweise', 'Preis', 'Zahlungsmethode',
            'Saldo', 'Verzehrguthabenausgleich', 'Bezahlt bis', 'archiviert',
        ];

        $rows = [$columns];

        foreach (array_merge([$spec['master']['primary']], $spec['master']['modules'] ?? []) as $row) {
            $rows[] = array_map(fn ($column) => (string) ($row[$column] ?? ''), $columns);
        }

        $this->writeSheet($folder.'/master_data.xlsx', $rows);

        if (isset($spec['account_rows'])) {
            $this->writeSheet($folder.'/account_data.xlsx', $spec['account_rows']);
        }

        if (isset($spec['access'])) {
            $this->writeSheet($folder.'/access_identifications.xlsx', $spec['access']);
        }

        foreach (['contracts', 'customer', 'bank_accounts', 'liable_person', 'contact'] as $key) {
            if (isset($spec[$key])) {
                file_put_contents($folder.'/'.$key.'.json', json_encode($spec[$key]));
            }
        }

        return $folder;
    }

    /**
     * Write a minimal XLSX file with inline strings.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeSheet(string $path, array $rows): void
    {
        $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $xml .= '<row r="'.($rowIndex + 1).'">';

            foreach (array_values($row) as $cellIndex => $value) {
                $reference = $this->columnLetter($cellIndex).($rowIndex + 1);
                $xml .= '<c r="'.$reference.'" t="inlineStr"><is><t>'
                    .htmlspecialchars((string) $value, ENT_XML1)
                    .'</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
    }

    private function columnLetter(int $index): string
    {
        $letters = '';

        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $letters = chr(65 + (($i - 1) % 26)).$letters;
        }

        return $letters;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
