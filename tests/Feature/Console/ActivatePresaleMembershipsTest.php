<?php

namespace Tests\Feature\Console;

use App\Mail\WelcomeMemberMail;
use App\Models\FraudCheck;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\MemberStatusService;
use App\Services\MollieService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pre-registrations for a gym opening: memberships cancelled by the pending
 * timeout are moved to the opening date, switched over to Mollie and activated,
 * and the member gets the welcome email.
 */
class ActivatePresaleMembershipsTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private MembershipPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-15 10:00:00');
        Mail::fake();
        Storage::fake('local');

        $this->gym = Gym::factory()->create([
            'mollie_config' => [
                'api_key' => 'test_key',
                'enabled_methods' => ['directdebit'],
            ],
        ]);
        $this->plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'commitment_months' => 12,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{0: Membership, 1: PaymentMethod}
     */
    private function preRegistration(string $startDate = '2026-10-01', array $membershipOverrides = []): array
    {
        $member = Member::factory()->create(['gym_id' => $this->gym->id, 'status' => 'pending']);
        $start = Carbon::parse($startDate);

        $membership = Membership::factory()->create(array_merge([
            'member_id' => $member->id,
            'membership_plan_id' => $this->plan->id,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addMonths(12)->subDay()->toDateString(),
            'status' => 'expired',
            'cancellation_reason' => Membership::PENDING_TIMEOUT_REASON,
        ], $membershipOverrides));

        $paymentMethod = PaymentMethod::create([
            'member_id' => $member->id,
            'type' => 'sepa_direct_debit',
            'status' => 'active',
            'is_default' => true,
            'requires_mandate' => true,
            'sepa_mandate_status' => 'active',
            'sepa_mandate_reference' => 'REF-'.$member->id,
            'iban' => 'DE02120300000000202051',
            'account_holder' => 'Erika Mustermann',
        ]);

        foreach (['setup_fee' => 39, 'initial' => 20] as $type => $amount) {
            $this->payment($membership, ['amount' => $amount, 'metadata' => ['payment_type' => $type]]);
        }

        return [$membership, $paymentMethod];
    }

    private function payment(Membership $membership, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'gym_id' => $this->gym->id,
            'membership_id' => $membership->id,
            'member_id' => $membership->member_id,
            'amount' => 20,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'status' => 'pending',
            'payment_method' => 'sepa_direct_debit',
            'due_date' => '2026-11-01',
            'execution_date' => '2026-11-04',
        ], $overrides));
    }

    /**
     * Stands in for Mollie creating the mandate right away, which activates
     * the member the same way the real service does.
     */
    private function mollieCreatesMandate(?callable $assertion = null): void
    {
        $this->mock(MollieService::class, function ($mock) use ($assertion) {
            $mock->shouldReceive('handleMolliePaymentMethod')
                ->andReturnUsing(function (Member $member, PaymentMethod $paymentMethod) use ($assertion) {
                    $assertion && $assertion($member, $paymentMethod);

                    $paymentMethod->update(['status' => 'active', 'mollie_mandate_id' => 'mdt_test', 'iban' => '']);
                    $member->update(['status' => 'active']);
                    app(MemberStatusService::class)->handleStatusChangeActions($member, 'pending', 'active', null, 'system');

                    return true;
                });
        });
    }

    private function activatePresale(array $options = []): PendingCommand
    {
        return $this->artisan('memberships:activate-presale', array_merge([
            'gym' => $this->gym->id,
            '--opening-date' => '2026-11-01',
            '--force' => true,
        ], $options));
    }

    #[Test]
    public function it_moves_an_october_pre_registration_to_the_opening_and_activates_it_via_mollie(): void
    {
        [$membership, $paymentMethod] = $this->preRegistration('2026-10-01');

        $this->mollieCreatesMandate(function (Member $member, PaymentMethod $handedOver) {
            // Mollie needs the account data, so it must still be there at this point
            $this->assertSame('mollie_directdebit', $handedOver->type);
            $this->assertSame('DE02120300000000202051', $handedOver->iban);
        });

        $this->activatePresale()->assertExitCode(0);

        $membership->refresh();
        $this->assertSame('active', $membership->status);
        $this->assertNull($membership->cancellation_reason);
        $this->assertSame('2026-11-01', $membership->start_date->toDateString());
        $this->assertSame('2027-10-31', $membership->end_date->toDateString());
        $this->assertSame('2026-10-01', $membership->metadata['presale_activation']['original_start_date']);

        $this->assertSame('active', $membership->member->fresh()->status);
        $this->assertSame('mollie_directdebit', $paymentMethod->fresh()->type);

        Mail::assertSent(WelcomeMemberMail::class, fn ($mail) => $mail->member->is($membership->member));
    }

    #[Test]
    public function it_switches_the_direct_debits_to_mollie_and_keeps_amounts_and_cash_payments(): void
    {
        [$membership] = $this->preRegistration('2026-11-01');
        $cash = $this->payment($membership, ['payment_method' => 'cash', 'amount' => 60, 'execution_date' => '2026-11-01']);

        $this->mollieCreatesMandate();

        $this->activatePresale()->assertExitCode(0);

        $directDebits = $membership->payments()->whereKeyNot($cash->id)->get();
        $this->assertCount(2, $directDebits);

        foreach ($directDebits as $payment) {
            $this->assertSame('mollie_directdebit', $payment->payment_method);
            $this->assertSame('2026-11-01', $payment->due_date->toDateString());
            // Due date plus the Mollie offset for a first payment
            $this->assertSame('2026-11-06', $payment->execution_date->toDateString());
        }
        $this->assertEqualsCanonicalizing(['39.00', '20.00'], $directDebits->pluck('amount')->all());

        $cash->refresh();
        $this->assertSame('cash', $cash->payment_method);
        $this->assertSame('2026-11-01', $cash->execution_date->toDateString());
    }

    #[Test]
    public function a_dry_run_changes_nothing(): void
    {
        [$membership, $paymentMethod] = $this->preRegistration('2026-10-01');

        $this->mock(MollieService::class, fn ($mock) => $mock->shouldNotReceive('handleMolliePaymentMethod'));

        $this->activatePresale(['--dry-run' => true, '--force' => false])
            ->expectsOutputToContain('ready: 1')
            ->assertExitCode(0);

        $membership->refresh();
        $this->assertSame('expired', $membership->status);
        $this->assertSame('2026-10-01', $membership->start_date->toDateString());
        $this->assertSame('sepa_direct_debit', $paymentMethod->fresh()->type);
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_skips_memberships_that_need_a_manual_look(): void
    {
        [$withCanceledPayment] = $this->preRegistration();
        $this->payment($withCanceledPayment, ['status' => 'canceled']);

        [$flagged] = $this->preRegistration();
        FraudCheck::create([
            'gym_id' => $this->gym->id,
            'member_id' => $flagged->member_id,
            'email' => 'flagged@example.com',
            'fraud_score' => 90,
            'matched_fields' => ['iban' => 100],
            'action' => 'flagged',
            'checked_at' => now(),
        ]);

        [$customEnd] = $this->preRegistration('2026-10-01', ['end_date' => '2027-03-31']);

        [$withoutMandate, $paymentMethod] = $this->preRegistration();
        $paymentMethod->update(['sepa_mandate_status' => 'pending']);

        $this->mock(MollieService::class, fn ($mock) => $mock->shouldNotReceive('handleMolliePaymentMethod'));

        $this->activatePresale()->expectsOutputToContain('skipped: 4')->assertExitCode(0);

        foreach ([$withCanceledPayment, $flagged, $customEnd, $withoutMandate] as $membership) {
            $this->assertSame('expired', $membership->fresh()->status);
        }
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_leaves_memberships_alone_that_were_not_cancelled_by_the_timeout(): void
    {
        [$manuallyExpired] = $this->preRegistration('2026-11-01', ['cancellation_reason' => null]);
        [$laterStart] = $this->preRegistration('2026-12-01');

        $otherGym = Gym::factory()->create();
        $foreign = Membership::factory()->create([
            'member_id' => Member::factory()->create(['gym_id' => $otherGym->id, 'status' => 'pending'])->id,
            'membership_plan_id' => MembershipPlan::factory()->create(['gym_id' => $otherGym->id])->id,
            'start_date' => '2026-10-01',
            'status' => 'expired',
            'cancellation_reason' => Membership::PENDING_TIMEOUT_REASON,
        ]);

        $this->mock(MollieService::class, fn ($mock) => $mock->shouldNotReceive('handleMolliePaymentMethod'));

        $this->activatePresale()->expectsOutputToContain('0 Voranmeldung(en)')->assertExitCode(0);

        $this->assertSame('expired', $manuallyExpired->fresh()->status);
        $this->assertSame('expired', $laterStart->fresh()->status);
        $this->assertSame('expired', $foreign->fresh()->status);
    }

    #[Test]
    public function a_membership_whose_mollie_step_failed_is_picked_up_again_on_the_next_run(): void
    {
        [$membership] = $this->preRegistration('2026-10-01');

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldReceive('handleMolliePaymentMethod')->once()->andReturnFalse();
        });

        $this->activatePresale()->expectsOutputToContain('failed: 1')->assertExitCode(1);

        $membership->refresh();
        $this->assertSame('pending', $membership->status);
        $this->assertSame('2026-11-01', $membership->start_date->toDateString());
        Mail::assertNothingSent();

        $this->mollieCreatesMandate();

        $this->activatePresale()->expectsOutputToContain('activated: 1')->assertExitCode(0);

        $membership->refresh();
        $this->assertSame('active', $membership->status);
        // The original state recorded on the first run survives the retry
        $this->assertSame('2026-10-01', $membership->metadata['presale_activation']['original_start_date']);
    }

    #[Test]
    public function it_refuses_a_gym_without_mollie_direct_debit(): void
    {
        $this->gym->update(['mollie_config' => null]);
        $this->preRegistration();

        $this->activatePresale()
            ->expectsOutputToContain('keine SEPA-Lastschrift über Mollie')
            ->assertExitCode(1);
    }
}
