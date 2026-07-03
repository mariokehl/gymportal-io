<?php

namespace Tests\Unit\Services;

use App\Services\GoogleSheetsService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GoogleSheetsServiceTest extends TestCase
{
    private GoogleSheetsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GoogleSheetsService;
    }

    #[Test]
    public function it_extracts_the_spreadsheet_id_from_various_url_formats(): void
    {
        $id = '1AbC-dEfGhIjKlMnOpQrStUvWxYz_1234567890';

        $this->assertSame($id, $this->service->extractSpreadsheetId(
            "https://docs.google.com/spreadsheets/d/{$id}/edit#gid=0"
        ));

        $this->assertSame($id, $this->service->extractSpreadsheetId(
            "https://docs.google.com/spreadsheets/d/{$id}/edit?usp=sharing"
        ));

        $this->assertSame($id, $this->service->extractSpreadsheetId(
            "https://docs.google.com/spreadsheets/d/{$id}"
        ));
    }

    #[Test]
    public function it_returns_null_for_an_invalid_sheet_url(): void
    {
        $this->assertNull($this->service->extractSpreadsheetId('https://example.com/not-a-sheet'));
        $this->assertNull($this->service->extractSpreadsheetId(''));
    }

    #[Test]
    public function it_validates_a_well_formed_service_account_key(): void
    {
        $valid = [
            'type' => 'service_account',
            'client_email' => 'sync@project.iam.gserviceaccount.com',
            'private_key' => '-----BEGIN PRIVATE KEY-----',
        ];

        $this->assertTrue($this->service->isValidServiceAccountKey($valid));
        $this->assertSame(
            'sync@project.iam.gserviceaccount.com',
            $this->service->serviceAccountEmailFrom($valid)
        );
    }

    #[Test]
    public function it_rejects_invalid_service_account_keys(): void
    {
        $this->assertFalse($this->service->isValidServiceAccountKey(null));
        $this->assertFalse($this->service->isValidServiceAccountKey([]));
        $this->assertFalse($this->service->isValidServiceAccountKey([
            'type' => 'authorized_user',
            'client_email' => 'x@y.z',
            'private_key' => 'k',
        ]));
        $this->assertFalse($this->service->isValidServiceAccountKey([
            'type' => 'service_account',
            'client_email' => 'x@y.z',
            // missing private_key
        ]));
    }
}
