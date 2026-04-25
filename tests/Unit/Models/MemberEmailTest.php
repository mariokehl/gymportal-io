<?php

namespace Tests\Unit\Models;

use App\Models\Member;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MemberEmailTest extends TestCase
{
    #[Test]
    public function synthetic_email_domain_constant_is_lowercase_with_leading_at(): void
    {
        $this->assertSame('@import.local', Member::SYNTHETIC_EMAIL_DOMAIN);
    }

    #[Test]
    #[DataProvider('syntheticEmailProvider')]
    public function is_synthetic_email_detects_import_local_addresses(?string $email, bool $expected): void
    {
        $this->assertSame($expected, Member::isSyntheticEmail($email));
    }

    public static function syntheticEmailProvider(): array
    {
        return [
            'null' => [null, false],
            'empty string' => ['', false],
            'real gmail address' => ['user@gmail.com', false],
            'lowercase synthetic' => ['36cc7e35-4cfb-402d-8c23-f136d5417281@import.local', true],
            'uppercase domain' => ['user@IMPORT.LOCAL', true],
            'mixed case domain' => ['user@Import.Local', true],
            'domain contains import.local but is different' => ['user@import.local.evil.com', false],
            'import.local as subdomain host is NOT our marker' => ['user@foo.import.local', false],
            'only the marker without local part' => ['@import.local', true],
            'looks similar but is not synthetic' => ['user@import-local.com', false],
        ];
    }

    #[Test]
    public function can_receive_emails_returns_false_when_email_is_null(): void
    {
        $member = new Member();
        $member->email = null;

        $this->assertFalse($member->canReceiveEmails());
    }

    #[Test]
    public function can_receive_emails_returns_false_for_synthetic_email(): void
    {
        $member = new Member();
        $member->email = 'uuid@import.local';

        $this->assertFalse($member->canReceiveEmails());
    }

    #[Test]
    public function can_receive_emails_returns_true_for_real_email(): void
    {
        $member = new Member();
        $member->email = 'real.user@example.com';

        $this->assertTrue($member->canReceiveEmails());
    }
}
