<?php

namespace Tests\Unit\Mail\Policies;

use App\Mail\Policies\MissingEmailPolicy;
use App\Models\Member;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Mail\Fixtures\DummyMemberMail;

class MissingEmailPolicyTest extends TestCase
{
    #[Test]
    public function name_is_stable_identifier(): void
    {
        $this->assertSame('missing_email', (new MissingEmailPolicy())->name());
    }

    #[Test]
    public function denies_when_member_has_no_email(): void
    {
        $member = new Member();
        $member->email = null;

        $decision = (new MissingEmailPolicy())->check($member, new DummyMemberMail());

        $this->assertFalse($decision->allowed);
        $this->assertSame('MEMBER_NO_EMAIL', $decision->errorCode);
        $this->assertNotEmpty($decision->reason);
    }

    #[Test]
    public function allows_when_member_has_any_email(): void
    {
        $member = new Member();
        $member->email = 'anything@example.com';

        $decision = (new MissingEmailPolicy())->check($member, new DummyMemberMail());

        $this->assertTrue($decision->allowed);
    }

    #[Test]
    public function allows_even_for_synthetic_address_since_that_is_a_different_policy(): void
    {
        // Diese Policy ist nur für "kein Email-Feld gesetzt" zuständig.
        // Synthetic wird von SyntheticEmailPolicy abgedeckt (SRP).
        $member = new Member();
        $member->email = 'uuid@import.local';

        $decision = (new MissingEmailPolicy())->check($member, new DummyMemberMail());

        $this->assertTrue($decision->allowed);
    }
}
