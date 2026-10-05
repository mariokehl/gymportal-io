<?php

namespace Tests\Unit\Services;

use App\Services\NfcTagService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NfcTagServiceTest extends TestCase
{
    public static function decimalCardNumbers(): array
    {
        return [
            'three bytes are padded to four' => ['8426334', '80935E00'],
            'two bytes are padded to four' => ['4660', '12340000'],
            'four bytes stay unchanged' => ['1000000001', '3B9ACA01'],
            'a lost leading zero nibble is restored' => ['134823519', '08093E5F'],
            'seven-byte uids are kept in full' => ['1303689068602870', '04A1B2C3D4E5F6'],
            'surrounding whitespace is ignored' => [' 8426334 ', '80935E00'],
        ];
    }

    #[Test]
    #[DataProvider('decimalCardNumbers')]
    public function it_converts_a_decimal_card_number_to_the_scanner_uid(string $decimal, string $expected): void
    {
        $this->assertSame($expected, (new NfcTagService)->decimalToUid($decimal));
    }

    #[Test]
    public function it_rejects_values_that_are_not_decimal(): void
    {
        $service = new NfcTagService;

        $this->assertNull($service->decimalToUid('04A1B2C3'));
        $this->assertNull($service->decimalToUid(''));
        $this->assertNull($service->decimalToUid('1234567890123456789'));
    }
}
