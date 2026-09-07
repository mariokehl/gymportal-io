<?php

namespace Tests\Feature\Web;

use App\Mail\CancellationConfirmationMail;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The cancellation modal distinguishes an ordinary cancellation (bound to the
 * commitment and notice periods) from an extraordinary one (issued for cause,
 * free date). MembershipController::cancel must honour that distinction.
 */
class MembershipCancellationTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::factory()->create(['name' => 'Administrator', 'slug' => 'admin']);
        Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Member, 2: Membership}
     */
    private function makeCancellableMembership(): array
    {
        $owner = User::factory()->create();
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'commitment_months' => 12,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);

        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
        ]);

        return [$owner->fresh(), $member, $membership];
    }

    private function cancel(User $owner, Member $member, Membership $membership, array $payload)
    {
        return $this->actingAs($owner)->put(
            route('members.memberships.cancel', [
                'member' => $member->id,
                'membership' => $membership->id,
            ]),
            $payload,
        );
    }

    #[Test]
    public function an_ordinary_cancellation_is_still_bound_to_the_commitment_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-10-31',
            'cancellation_reason' => 'move',
            'cancellation_type' => 'ordinary',
            'immediate' => false,
        ])->assertSessionHasErrors('cancellation_date');

        $this->assertNull($membership->fresh()->cancellation_date);
    }

    #[Test]
    public function an_extraordinary_cancellation_accepts_a_free_future_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        // Well inside the 12-month commitment: only allowed for cause.
        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-10-31',
            'cancellation_reason' => 'health',
            'cancellation_type' => 'extraordinary',
            'immediate' => false,
        ])->assertSessionHasNoErrors();

        $membership->refresh();
        $this->assertSame('2026-10-31', $membership->cancellation_date->toDateString());
        $this->assertStringContainsString('Außerordentliche Kündigung', $membership->cancellation_reason);
        // Not immediate: the membership stays active until that date.
        $this->assertSame('active', $membership->status);
    }

    #[Test]
    public function an_immediate_cancellation_ends_the_membership_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-09-15',
            'cancellation_reason' => 'other',
            'cancellation_type' => 'extraordinary',
            'immediate' => true,
        ])->assertSessionHasNoErrors();

        $membership->refresh();
        $this->assertSame('cancelled', $membership->status);
        $this->assertSame('2026-09-15', $membership->cancellation_date->toDateString());
        $this->assertSame('2026-09-15', $membership->end_date->toDateString());
    }

    #[Test]
    public function a_reason_note_replaces_the_generic_qualifier(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-10-31',
            'cancellation_reason' => 'other',
            'cancellation_reason_note' => 'Kündigung wurde erst nach der Frist gelesen',
            'cancellation_type' => 'extraordinary',
            'immediate' => false,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'Sonstiges (Kündigung wurde erst nach der Frist gelesen)',
            $membership->fresh()->cancellation_reason,
        );
    }

    #[Test]
    public function a_reason_note_also_replaces_the_qualifier_on_an_immediate_cancellation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-09-15',
            'cancellation_reason' => 'other',
            'cancellation_reason_note' => 'Studio dauerhaft geschlossen',
            'cancellation_type' => 'extraordinary',
            'immediate' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'Sonstiges (Studio dauerhaft geschlossen)',
            $membership->fresh()->cancellation_reason,
        );
    }

    #[Test]
    public function an_empty_reason_note_keeps_the_generic_qualifier(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-10-31',
            'cancellation_reason' => 'other',
            'cancellation_reason_note' => '   ',
            'cancellation_type' => 'extraordinary',
            'immediate' => false,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'Sonstiges (Außerordentliche Kündigung)',
            $membership->fresh()->cancellation_reason,
        );
    }

    #[Test]
    public function a_reason_note_is_ignored_for_any_reason_other_than_sonstiges(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-10-31',
            'cancellation_reason' => 'health',
            'cancellation_reason_note' => 'sollte nicht erscheinen',
            'cancellation_type' => 'extraordinary',
            'immediate' => false,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'Gesundheitliche Gründe (Außerordentliche Kündigung)',
            $membership->fresh()->cancellation_reason,
        );
    }

    #[Test]
    public function a_reason_note_is_rejected_when_it_exceeds_the_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-10-31',
            'cancellation_reason' => 'other',
            'cancellation_reason_note' => str_repeat('a', 256),
            'cancellation_type' => 'extraordinary',
            'immediate' => false,
        ])->assertSessionHasErrors('cancellation_reason_note');

        $this->assertNull($membership->fresh()->cancellation_date);
    }

    #[Test]
    public function a_confirmation_mail_is_sent_when_requested(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-10-31',
            'cancellation_reason' => 'move',
            'cancellation_type' => 'extraordinary',
            'send_confirmation' => true,
            'immediate' => false,
        ])->assertSessionHasNoErrors();

        Mail::assertSent(CancellationConfirmationMail::class);
    }

    #[Test]
    public function no_confirmation_mail_is_sent_when_not_requested(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-10-31',
            'cancellation_reason' => 'move',
            'cancellation_type' => 'extraordinary',
            'send_confirmation' => false,
            'immediate' => false,
        ])->assertSessionHasNoErrors();

        Mail::assertNothingSent();

        // The cancellation itself is stored regardless of the mail.
        $this->assertSame('2026-10-31', $membership->fresh()->cancellation_date->toDateString());
    }

    #[Test]
    public function a_confirmation_mail_is_sent_for_an_immediate_cancellation_when_requested(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-09-15',
            'cancellation_reason' => 'other',
            'cancellation_type' => 'extraordinary',
            'send_confirmation' => true,
            'immediate' => true,
        ])->assertSessionHasNoErrors();

        Mail::assertSent(CancellationConfirmationMail::class);
        $this->assertSame('cancelled', $membership->fresh()->status);
    }

    #[Test]
    public function a_rejected_cancellation_never_sends_a_confirmation_mail(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-09-15'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        // Inside the commitment period an ordinary cancellation is rejected.
        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-10-31',
            'cancellation_reason' => 'move',
            'cancellation_type' => 'ordinary',
            'send_confirmation' => true,
            'immediate' => false,
        ])->assertSessionHasErrors('cancellation_date');

        Mail::assertNothingSent();
    }

    #[Test]
    public function an_ordinary_cancellation_to_the_next_possible_date_is_accepted(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-06-01'));

        [$owner, $member, $membership] = $this->makeCancellableMembership();

        // Commitment ends 31.08.2027, notice period is one month — the date the
        // modal pre-fills for an ordinary cancellation.
        $this->assertSame('2027-08-31', $membership->next_possible_cancellation_date);

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => $membership->next_possible_cancellation_date,
            'cancellation_reason' => 'no_time',
            'cancellation_type' => 'ordinary',
            'immediate' => false,
        ])->assertSessionHasNoErrors();

        $membership->refresh();
        $this->assertSame('2027-08-31', $membership->cancellation_date->toDateString());
        $this->assertSame('Zeitmangel', $membership->cancellation_reason);
    }
}
