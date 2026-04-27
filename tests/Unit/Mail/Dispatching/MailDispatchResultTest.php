<?php

namespace Tests\Unit\Mail\Dispatching;

use App\Mail\Dispatching\MailDispatchResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MailDispatchResultTest extends TestCase
{
    #[Test]
    public function sent_result_reports_sent_status(): void
    {
        $result = MailDispatchResult::sent();

        $this->assertTrue($result->wasSent());
        $this->assertFalse($result->wasSkipped());
        $this->assertFalse($result->hasFailed());
        $this->assertSame('sent', $result->status);
        $this->assertNull($result->reason);
        $this->assertNull($result->errorCode);
    }

    #[Test]
    public function skipped_result_carries_reason_and_error_code(): void
    {
        $result = MailDispatchResult::skipped('no email', 'MEMBER_NO_EMAIL');

        $this->assertTrue($result->wasSkipped());
        $this->assertFalse($result->wasSent());
        $this->assertFalse($result->hasFailed());
        $this->assertSame('no email', $result->reason);
        $this->assertSame('MEMBER_NO_EMAIL', $result->errorCode);
    }

    #[Test]
    public function skipped_result_allows_null_error_code(): void
    {
        $result = MailDispatchResult::skipped('opaque reason');

        $this->assertTrue($result->wasSkipped());
        $this->assertNull($result->errorCode);
        $this->assertSame('opaque reason', $result->reason);
    }

    #[Test]
    public function failed_result_reports_failure(): void
    {
        $result = MailDispatchResult::failed('SMTP 550');

        $this->assertTrue($result->hasFailed());
        $this->assertFalse($result->wasSent());
        $this->assertFalse($result->wasSkipped());
        $this->assertSame('SMTP 550', $result->reason);
    }

    #[Test]
    public function status_values_are_mutually_exclusive(): void
    {
        $variants = [
            MailDispatchResult::sent(),
            MailDispatchResult::skipped('x'),
            MailDispatchResult::failed('y'),
        ];

        foreach ($variants as $result) {
            $true = (int) $result->wasSent()
                + (int) $result->wasSkipped()
                + (int) $result->hasFailed();

            $this->assertSame(1, $true, "Status {$result->status} reports more than one state");
        }
    }
}
