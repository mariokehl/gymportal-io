<?php

namespace Tests\Feature\Services;

use App\Events\ContractWithdrawn;
use App\Mail\WithdrawalConfirmationMail;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\MembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MembershipService::withdraw is shared by the admin area and the PWA, so the
 * parts both callers rely on — status, refund, linked free trial, confirmation
 * mail — are covered here rather than twice at the controller level.
 */
class MembershipWithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Carbon::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function service(): MembershipService
    {
        return app(MembershipService::class);
    }

    /**
     * @return array{0: Member, 1: Membership, 2: Membership}
     */
    private function makeContractWithTrial(array $overrides = []): array
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $trialPlan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'is_free_trial_plan' => true,
        ]);

        $paidPlan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'is_free_trial_plan' => false,
        ]);

        $freeTrial = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $trialPlan->id,
            'status' => 'active',
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-30',
        ]);

        $membership = Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $paidPlan->id,
            'status' => 'active',
            'start_date' => '2026-10-01',
            'end_date' => '2027-09-30',
            'linked_free_membership_id' => $freeTrial->id,
        ], $overrides));

        return [$member, $membership, $freeTrial];
    }

    #[Test]
    public function it_withdraws_the_membership_and_expires_the_linked_free_trial(): void
    {
        [, $membership, $freeTrial] = $this->makeContractWithTrial();

        $this->service()->withdraw($membership, 'kunde@example.test', 'admin_withdrawal');

        $membership->refresh();

        $this->assertSame('withdrawn', $membership->status);
        $this->assertNotNull($membership->withdrawn_at);
        $this->assertSame('kunde@example.test', $membership->withdrawal_confirmation_sent_to);

        $freeTrial->refresh();

        $this->assertSame('expired', $freeTrial->status);
        $this->assertSame('2026-09-08', $freeTrial->end_date->format('Y-m-d'));
        $this->assertSame('admin_withdrawal', $freeTrial->metadata['expired_by'] ?? null);
    }

    #[Test]
    public function it_appends_the_given_note_and_leaves_the_notes_alone_without_one(): void
    {
        [, $membership] = $this->makeContractWithTrial(['notes' => 'Bestehende Notiz']);

        $this->service()->withdraw($membership, null, 'admin_withdrawal', note: 'Widerrufen am 08.09.2026');

        $this->assertSame(
            "Bestehende Notiz\nWiderrufen am 08.09.2026",
            $membership->fresh()->notes,
        );

        [, $second] = $this->makeContractWithTrial(['notes' => 'Nur diese Notiz']);

        $this->service()->withdraw($second, null, 'pwa_withdrawal');

        $this->assertSame('Nur diese Notiz', $second->fresh()->notes);
    }

    #[Test]
    public function it_only_dispatches_the_staff_event_when_asked_to(): void
    {
        Event::fake([ContractWithdrawn::class]);

        [, $silent] = $this->makeContractWithTrial();

        $this->service()->withdraw($silent, null, 'admin_withdrawal');

        Event::assertNotDispatched(ContractWithdrawn::class);

        [, $loud] = $this->makeContractWithTrial();

        $this->service()->withdraw($loud, null, 'pwa_withdrawal', dispatchEvent: true);

        Event::assertDispatched(ContractWithdrawn::class);
    }

    #[Test]
    public function it_sends_the_confirmation_mail_to_the_given_address(): void
    {
        [, $membership] = $this->makeContractWithTrial();

        $this->service()->withdraw($membership, 'widerruf@example.test', 'pwa_withdrawal');

        Mail::assertSent(
            WithdrawalConfirmationMail::class,
            fn ($mail) => $mail->hasTo('widerruf@example.test'),
        );
    }

    #[Test]
    public function a_free_trial_plan_cannot_be_withdrawn(): void
    {
        [, , $freeTrial] = $this->makeContractWithTrial();

        $result = $this->service()->checkWithdrawalEligibility($freeTrial);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Kostenlose Mitgliedschaften', $result['reason']);
    }

    #[Test]
    public function an_already_withdrawn_membership_cannot_be_withdrawn_again(): void
    {
        [, $membership] = $this->makeContractWithTrial();

        $membership->update(['withdrawn_at' => now()]);

        $result = $this->service()->checkWithdrawalEligibility($membership);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('bereits widerrufen', $result['reason']);
    }

    #[Test]
    public function a_cancelled_membership_cannot_be_withdrawn(): void
    {
        [, $membership] = $this->makeContractWithTrial(['status' => 'cancelled']);

        $result = $this->service()->checkWithdrawalEligibility($membership);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Gekündigte Verträge', $result['reason']);
    }

    #[Test]
    public function the_deadline_runs_from_the_linked_free_trial_start(): void
    {
        [, $membership] = $this->makeContractWithTrial();

        // The trial starts 2026-09-05, so the 14-day period ends 2026-09-19.
        $this->assertSame(
            '2026-09-19 23:59:59',
            $this->service()->withdrawalDeadline($membership)->format('Y-m-d H:i:s'),
        );

        $this->assertTrue($this->service()->checkWithdrawalEligibility($membership)['eligible']);

        Carbon::setTestNow('2026-09-20 08:00:00');

        $result = $this->service()->checkWithdrawalEligibility($membership);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Widerrufsfrist ist bereits abgelaufen', $result['reason']);
    }
}
