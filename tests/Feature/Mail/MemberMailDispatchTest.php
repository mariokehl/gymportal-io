<?php

namespace Tests\Feature\Mail;

use App\Mail\Dispatching\MemberMailDispatcher;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Mail\Fixtures\DummyMemberMail;

/**
 * End-to-End Test des MemberMailDispatcher inkl. Container-Bindung
 * und der real registrierten Policies aus dem AppServiceProvider.
 *
 * Im Gegensatz zu den Unit-Tests in tests/Unit/Mail/Dispatching wird
 * der Dispatcher hier nicht manuell konstruiert, sondern aus dem
 * Service-Container aufgelöst — das prüft, dass die Policy-Liste
 * im Provider korrekt verdrahtet ist.
 */
class MemberMailDispatchTest extends TestCase
{
    private function makeMember(?string $email): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = $email;

        return $member;
    }

    #[Test]
    public function dispatcher_is_resolvable_from_container(): void
    {
        $dispatcher = $this->app->make(MemberMailDispatcher::class);

        $this->assertInstanceOf(MemberMailDispatcher::class, $dispatcher);
    }

    #[Test]
    public function sends_mail_to_member_with_real_email(): void
    {
        Mail::fake();

        $dispatcher = $this->app->make(MemberMailDispatcher::class);

        $result = $dispatcher->sendToMember(
            $this->makeMember('member@example.com'),
            new DummyMemberMail(),
        );

        $this->assertTrue($result->wasSent());
        Mail::assertSent(DummyMemberMail::class, function ($mail) {
            return $mail->hasTo('member@example.com');
        });
    }

    #[Test]
    public function skips_when_member_email_is_missing(): void
    {
        Mail::fake();

        $dispatcher = $this->app->make(MemberMailDispatcher::class);

        $result = $dispatcher->sendToMember(
            $this->makeMember(null),
            new DummyMemberMail(),
        );

        $this->assertTrue($result->wasSkipped());
        Mail::assertNothingSent();
    }

    #[Test]
    public function skips_when_member_email_is_synthetic(): void
    {
        Mail::fake();

        $dispatcher = $this->app->make(MemberMailDispatcher::class);

        $result = $dispatcher->sendToMember(
            $this->makeMember('uuid@import.local'),
            new DummyMemberMail(),
        );

        $this->assertTrue($result->wasSkipped());
        $this->assertSame('MEMBER_SYNTHETIC_EMAIL', $result->errorCode);
        Mail::assertNothingSent();
    }

    #[Test]
    public function send_to_address_overrides_member_email(): void
    {
        Mail::fake();

        $dispatcher = $this->app->make(MemberMailDispatcher::class);

        $result = $dispatcher->sendToAddress(
            $this->makeMember('member@example.com'),
            new DummyMemberMail(),
            'override@example.com',
        );

        $this->assertTrue($result->wasSent());
        Mail::assertSent(DummyMemberMail::class, function ($mail) {
            return $mail->hasTo('override@example.com')
                && !$mail->hasTo('member@example.com');
        });
    }

    #[Test]
    public function send_to_address_skips_when_address_is_synthetic(): void
    {
        Mail::fake();

        $dispatcher = $this->app->make(MemberMailDispatcher::class);

        $result = $dispatcher->sendToAddress(
            $this->makeMember('member@example.com'),
            new DummyMemberMail(),
            'synthetic@import.local',
        );

        $this->assertTrue($result->wasSkipped());
        Mail::assertNothingSent();
    }

    #[Test]
    public function send_to_address_skips_when_address_is_null(): void
    {
        Mail::fake();

        $dispatcher = $this->app->make(MemberMailDispatcher::class);

        $result = $dispatcher->sendToAddress(
            $this->makeMember('member@example.com'),
            new DummyMemberMail(),
            null,
        );

        $this->assertTrue($result->wasSkipped());
        $this->assertSame('MISSING_ADDRESS', $result->errorCode);
        Mail::assertNothingSent();
    }
}
