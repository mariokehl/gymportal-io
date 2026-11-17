<?php

namespace Tests\Unit\Services;

use App\Services\MollieService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MollieChargebackFeeTest extends TestCase
{
    public static function chargebackCases(): array
    {
        return [
            'direct debit reversed by account holder' => ['directdebit', 'MD06', 10.00],
            'direct debit reason code is case-insensitive' => ['directdebit', ' md06 ', 10.00],
            'direct debit with invalid IBAN' => ['directdebit', 'AC01', 0.95],
            'direct debit with insufficient funds' => ['directdebit', 'AM04', 0.95],
            'direct debit refused by debtor' => ['directdebit', 'MS03', 0.95],
            'direct debit without reason code has no fee' => ['directdebit', null, 0.00],
            'direct debit with empty reason code has no fee' => ['directdebit', '', 0.00],
            'disputed credit card payment' => ['creditcard', null, 25.00],
            'credit card ignores reason code' => ['creditcard', 'AC01', 25.00],
            'unknown method has no fee' => ['paypal', 'MD06', 0.00],
            'missing method has no fee' => [null, null, 0.00],
        ];
    }

    #[Test]
    #[DataProvider('chargebackCases')]
    public function it_determines_the_fee_from_method_and_reason_code(?string $method, ?string $reasonCode, float $expectedFee): void
    {
        $this->assertSame($expectedFee, (new MollieService)->getChargebackFee($method, $reasonCode));
    }
}
