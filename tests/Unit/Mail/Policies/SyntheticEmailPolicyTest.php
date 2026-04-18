<?php

namespace Tests\Unit\Mail\Policies;

use App\Mail\Policies\SyntheticEmailPolicy;
use App\Models\Member;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Mail\Fixtures\DummyMemberMail;

class SyntheticEmailPolicyTest extends TestCase
{
    #[Test]
    public function name_is_stable_identifier(): void
    {
        $this->assertSame('synthetic_email', (new SyntheticEmailPolicy())->name());
    }

    #[Test]
    public function denies_synthetic_import_address(): void
    {
        $member = new Member();
        $member->email = '36cc7e35-4cfb-402d-8c23-f136d5417281@import.local';

        $decision = (new SyntheticEmailPolicy())->check($member, new DummyMemberMail());

        $this->assertFalse($decision->allowed);
        $this->assertSame('MEMBER_SYNTHETIC_EMAIL', $decision->errorCode);
    }

    #[Test]
    public function denies_synthetic_even_when_domain_is_uppercase(): void
    {
        $member = new Member();
        $member->email = 'user@IMPORT.LOCAL';

        $decision = (new SyntheticEmailPolicy())->check($member, new DummyMemberMail());

        $this->assertFalse($decision->allowed);
    }

    #[Test]
    public function allows_real_email(): void
    {
        $member = new Member();
        $member->email = 'real@example.com';

        $decision = (new SyntheticEmailPolicy())->check($member, new DummyMemberMail());

        $this->assertTrue($decision->allowed);
    }

    #[Test]
    public function allows_empty_email_since_that_is_a_different_policy(): void
    {
        // SRP: Diese Policy prüft NUR auf Synthetik, nicht auf Fehlen.
        // MissingEmailPolicy übernimmt den Fehlen-Fall; der Dispatcher
        // kettet beide Policies zusammen.
        $member = new Member();
        $member->email = null;

        $decision = (new SyntheticEmailPolicy())->check($member, new DummyMemberMail());

        $this->assertTrue($decision->allowed);
    }
}
