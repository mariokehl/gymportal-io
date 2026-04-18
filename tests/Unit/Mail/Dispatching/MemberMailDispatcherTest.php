<?php

namespace Tests\Unit\Mail\Dispatching;

use App\Mail\Contracts\MemberMail;
use App\Mail\Dispatching\MemberMailDispatcher;
use App\Mail\Policies\MemberMailPolicy;
use App\Mail\Policies\MissingEmailPolicy;
use App\Mail\Policies\PolicyDecision;
use App\Mail\Policies\SyntheticEmailPolicy;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Tests\Unit\Mail\Fixtures\DummyMemberMail;

class MemberMailDispatcherTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeMember(int $id = 42, ?string $email = 'real@example.com'): Member
    {
        $member = new Member();
        $member->id = $id;
        $member->email = $email;

        return $member;
    }

    private function makeDispatcher(array $policies): MemberMailDispatcher
    {
        return new MemberMailDispatcher($policies);
    }

    #[Test]
    public function sends_to_member_when_all_policies_allow(): void
    {
        Mail::fake();

        $dispatcher = $this->makeDispatcher([
            new MissingEmailPolicy(),
            new SyntheticEmailPolicy(),
        ]);

        $result = $dispatcher->sendToMember($this->makeMember(), new DummyMemberMail());

        $this->assertTrue($result->wasSent());
        Mail::assertSent(DummyMemberMail::class, 1);
        Mail::assertSent(DummyMemberMail::class, function ($mail) {
            return $mail->hasTo('real@example.com');
        });
    }

    #[Test]
    public function skips_when_member_has_no_email(): void
    {
        Mail::fake();

        $dispatcher = $this->makeDispatcher([
            new MissingEmailPolicy(),
            new SyntheticEmailPolicy(),
        ]);

        $member = $this->makeMember(email: null);

        $result = $dispatcher->sendToMember($member, new DummyMemberMail());

        $this->assertTrue($result->wasSkipped());
        Mail::assertNothingSent();
    }

    #[Test]
    public function skips_when_member_has_synthetic_email(): void
    {
        Mail::fake();

        $dispatcher = $this->makeDispatcher([
            new MissingEmailPolicy(),
            new SyntheticEmailPolicy(),
        ]);

        $member = $this->makeMember(email: 'uuid@import.local');

        $result = $dispatcher->sendToMember($member, new DummyMemberMail());

        $this->assertTrue($result->wasSkipped());
        $this->assertSame('MEMBER_SYNTHETIC_EMAIL', $result->errorCode);
        Mail::assertNothingSent();
    }

    #[Test]
    public function skip_by_address_check_triggers_before_any_policy_runs(): void
    {
        Mail::fake();

        // Policy, die explodieren würde, wenn sie aufgerufen wird:
        $neverCalled = $this->mock(MemberMailPolicy::class, function ($mock) {
            $mock->shouldReceive('check')->never();
            $mock->shouldReceive('name')->andReturn('never-called');
        });

        $dispatcher = $this->makeDispatcher([$neverCalled]);

        $member = $this->makeMember(email: 'synthetic@import.local');

        $result = $dispatcher->sendToMember($member, new DummyMemberMail());

        $this->assertTrue($result->wasSkipped());
        Mail::assertNothingSent();
    }

    #[Test]
    public function stops_at_first_denying_policy(): void
    {
        Mail::fake();

        $denying = $this->mock(MemberMailPolicy::class, function ($mock) {
            $mock->shouldReceive('check')
                ->once()
                ->andReturn(PolicyDecision::deny('blocked for testing', 'TEST_DENY'));
            $mock->shouldReceive('name')->andReturn('test-deny');
        });

        // Zweite Policy darf nicht mehr befragt werden:
        $afterDeny = $this->mock(MemberMailPolicy::class, function ($mock) {
            $mock->shouldReceive('check')->never();
            $mock->shouldReceive('name')->andReturn('after-deny');
        });

        $dispatcher = $this->makeDispatcher([$denying, $afterDeny]);

        $result = $dispatcher->sendToMember($this->makeMember(), new DummyMemberMail());

        $this->assertTrue($result->wasSkipped());
        $this->assertSame('blocked for testing', $result->reason);
        $this->assertSame('TEST_DENY', $result->errorCode);
        Mail::assertNothingSent();
    }

    #[Test]
    public function new_policy_plugs_in_via_polymorphism(): void
    {
        // Kein Code-Change am Dispatcher nötig — eine beliebige Policy-Klasse
        // kann das Verhalten erweitern. Proof-of-Concept für Offen/Geschlossen.
        Mail::fake();

        $customPolicy = new class () implements MemberMailPolicy {
            public function name(): string
            {
                return 'vip_only';
            }

            public function check(Member $member, MemberMail $mail): PolicyDecision
            {
                return $member->id === 1
                    ? PolicyDecision::allow()
                    : PolicyDecision::deny('not VIP', 'NOT_VIP');
            }
        };

        $dispatcher = $this->makeDispatcher([$customPolicy]);

        $vip = $this->makeDispatcher([$customPolicy])->sendToMember(
            $this->makeMember(id: 1),
            new DummyMemberMail(),
        );
        $nonVip = $dispatcher->sendToMember(
            $this->makeMember(id: 2),
            new DummyMemberMail(),
        );

        $this->assertTrue($vip->wasSent());
        $this->assertTrue($nonVip->wasSkipped());
        $this->assertSame('NOT_VIP', $nonVip->errorCode);
    }

    #[Test]
    public function returns_failed_when_mailer_throws(): void
    {
        // Mail::fake() swallowt Exceptions — hier brauchen wir einen echten
        // Mailer-Mock, der eine Transport-Exception wirft. Mail::to() geht
        // über den MailManager/Facade-Root, daher swap'en wir dort.
        $pendingMail = Mockery::mock();
        $pendingMail->shouldReceive('send')->andThrow(new RuntimeException('SMTP down'));

        $mailer = Mockery::mock();
        $mailer->shouldReceive('to')->andReturn($pendingMail);

        Mail::swap($mailer);

        $dispatcher = $this->makeDispatcher([new MissingEmailPolicy(), new SyntheticEmailPolicy()]);

        $result = $dispatcher->sendToMember($this->makeMember(), new DummyMemberMail());

        $this->assertTrue($result->hasFailed());
        $this->assertSame('SMTP down', $result->reason);
    }

    #[Test]
    public function send_to_address_uses_explicit_address(): void
    {
        Mail::fake();

        $dispatcher = $this->makeDispatcher([
            new MissingEmailPolicy(),
            new SyntheticEmailPolicy(),
        ]);

        $result = $dispatcher->sendToAddress(
            $this->makeMember(),
            new DummyMemberMail(),
            'override@example.com',
        );

        $this->assertTrue($result->wasSent());
        Mail::assertSent(DummyMemberMail::class, function ($mail) {
            return $mail->hasTo('override@example.com')
                && !$mail->hasTo('real@example.com');
        });
    }

    #[Test]
    public function send_to_address_skips_when_address_is_null(): void
    {
        Mail::fake();

        $dispatcher = $this->makeDispatcher([
            new MissingEmailPolicy(),
            new SyntheticEmailPolicy(),
        ]);

        $result = $dispatcher->sendToAddress(
            $this->makeMember(),
            new DummyMemberMail(),
            null,
        );

        $this->assertTrue($result->wasSkipped());
        $this->assertSame('MISSING_ADDRESS', $result->errorCode);
        Mail::assertNothingSent();
    }

    #[Test]
    public function send_to_address_skips_when_address_is_synthetic_even_if_member_email_is_real(): void
    {
        Mail::fake();

        $dispatcher = $this->makeDispatcher([
            new MissingEmailPolicy(),
            new SyntheticEmailPolicy(),
        ]);

        $result = $dispatcher->sendToAddress(
            $this->makeMember(email: 'real@example.com'),
            new DummyMemberMail(),
            'synthetic@import.local',
        );

        $this->assertTrue($result->wasSkipped());
        Mail::assertNothingSent();
    }
}
