<?php

namespace Tests\Feature\Console;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the recurring add-on charges created by ProcessMembershipPayments.
 *
 * Recurring add-ons are billed in sync with the membership fee, so they share
 * the fee's due date for each period. Included add-ons are part of the plan and
 * never billed; a trial skips the booking month; a cancellation stops billing
 * after the effective date.
 */
class RecurringAddonBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-21 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * An active monthly membership starting on 2026-07-21.
     *
     * @return array{0: Membership, 1: Gym}
     */
    private function activeMembership(): array
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'price' => 49.99,
            'billing_cycle' => 'monthly',
            'is_free_trial_plan' => false,
            'trial_period_days' => 0,
        ]);

        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-07-21',
            'end_date' => null,
            'status' => 'active',
        ]);

        return [$membership, $gym];
    }

    private function bookAddon(Membership $membership, Addon $addon, array $pivot = []): void
    {
        $membership->addons()->attach($addon->id, array_merge([
            'mode' => 'optional',
            'price' => $addon->price,
            'created_at' => now(),
            'updated_at' => now(),
        ], $pivot));
    }

    private function runBilling(int $daysAhead = 14): void
    {
        $this->artisan('memberships:process-payments', ['--days' => $daysAhead])
            ->assertSuccessful();
    }

    /**
     * @return Collection<int, Payment>
     */
    private function addonPayments(Addon $addon)
    {
        return Payment::where('metadata->payment_type', 'addon_recurring')
            ->where('metadata->addon_id', $addon->id)
            ->orderBy('due_date')
            ->get();
    }

    #[Test]
    public function it_bills_a_recurring_addon_on_the_membership_due_date(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);
        $this->bookAddon($membership, $addon);

        $this->runBilling();

        $payments = $this->addonPayments($addon);

        $this->assertCount(1, $payments);
        $this->assertEquals(8.62, (float) $payments[0]->amount);

        // Billing is anchored to the membership start date, not the 1st.
        $this->assertSame('2026-07-21', $payments[0]->due_date->toDateString());
        $this->assertSame($membership->id, $payments[0]->membership_id);
    }

    #[Test]
    public function the_addon_shares_the_due_date_of_the_membership_fee(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);
        $this->bookAddon($membership, $addon);

        $this->runBilling();

        $planPayment = Payment::where('membership_id', $membership->id)
            ->where('metadata->payment_type', 'recurring')
            ->orderBy('due_date')
            ->first();

        $this->assertNotNull($planPayment, 'expected a membership fee payment');
        $this->assertSame(
            $planPayment->due_date->toDateString(),
            $this->addonPayments($addon)[0]->due_date->toDateString()
        );
    }

    #[Test]
    public function it_is_idempotent_across_runs(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);
        $this->bookAddon($membership, $addon);

        $this->runBilling();
        $this->runBilling();
        $this->runBilling();

        $this->assertCount(1, $this->addonPayments($addon));
    }

    #[Test]
    public function it_never_bills_an_included_addon(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);

        // The list price stays on the add-on; only the pivot records that it is
        // part of the plan. No payment must be created at all — not even a
        // zero-amount one.
        $this->bookAddon($membership, $addon, ['mode' => 'included', 'price' => 0]);

        $this->runBilling();

        $this->assertCount(0, $this->addonPayments($addon));
        $this->assertSame(0, Payment::where('membership_id', $membership->id)
            ->where('metadata->addon_id', $addon->id)
            ->count());
    }

    #[Test]
    public function it_bills_only_the_optional_addon_of_a_mixed_booking(): void
    {
        [$membership, $gym] = $this->activeMembership();

        $included = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 60]);
        $optional = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);

        $this->bookAddon($membership, $included, ['mode' => 'included', 'price' => 0]);
        $this->bookAddon($membership, $optional);

        $this->runBilling();

        $this->assertCount(0, $this->addonPayments($included));
        $this->assertCount(1, $this->addonPayments($optional));
    }

    #[Test]
    public function it_does_not_duplicate_the_signup_charge_of_the_first_period(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);
        $this->bookAddon($membership, $addon);

        // The widget already charged the first period at signup.
        Payment::create([
            'gym_id' => $gym->id,
            'membership_id' => $membership->id,
            'member_id' => $membership->member_id,
            'amount' => 8.62,
            'currency' => 'EUR',
            'description' => "Add-on: {$addon->name}",
            'status' => 'pending',
            'due_date' => $membership->start_date,
            'metadata' => [
                'payment_type' => 'addon',
                'addon_id' => $addon->id,
            ],
        ]);

        $this->runBilling();

        // The scheduler must not charge the same period a second time.
        $this->assertSame(1, Payment::where('membership_id', $membership->id)
            ->where('metadata->addon_id', $addon->id)
            ->whereDate('due_date', $membership->start_date)
            ->count());
    }

    #[Test]
    public function it_never_bills_a_one_time_addon(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->create(['gym_id' => $gym->id, 'price' => 60]);
        $this->bookAddon($membership, $addon);

        $this->runBilling();

        $this->assertCount(0, $this->addonPayments($addon));
    }

    #[Test]
    public function a_trial_skips_the_booking_month(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->usageFlatRate()->create([
            'gym_id' => $gym->id,
            'price' => 8.62,
            'trial_rest_of_month' => true,
        ]);
        $this->bookAddon($membership, $addon);

        // The 21.07. period falls inside the free rest of July.
        $this->runBilling();
        $this->assertCount(0, $this->addonPayments($addon));

        // The following period is charged again.
        Carbon::setTestNow('2026-08-21 08:00:00');
        $this->runBilling();

        $payments = $this->addonPayments($addon);
        $this->assertCount(1, $payments);
        $this->assertSame('2026-08-21', $payments[0]->due_date->toDateString());
    }

    #[Test]
    public function a_cancelled_addon_is_billed_up_to_the_effective_date_only(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);

        // Cancelled to the end of the current period (21.07.–20.08.).
        $this->bookAddon($membership, $addon, [
            'cancelled_at' => now(),
            'cancellation_effective_at' => '2026-08-20',
        ]);

        $this->runBilling();
        $this->assertCount(1, $this->addonPayments($addon), 'the running period is still billed');

        // The period starting 21.08. is after the effective date and must not
        // be billed any more.
        Carbon::setTestNow('2026-08-21 08:00:00');
        $this->runBilling();

        $payments = $this->addonPayments($addon);
        $this->assertCount(1, $payments);
        $this->assertSame('2026-07-21', $payments[0]->due_date->toDateString());
    }

    #[Test]
    public function it_bills_the_price_snapshot_from_the_booking(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);
        $this->bookAddon($membership, $addon, ['price' => 8.62]);

        // The add-on gets more expensive after the booking.
        $addon->update(['price' => 12.00]);

        $this->runBilling();

        $this->assertEquals(8.62, (float) $this->addonPayments($addon)[0]->amount);
    }

    #[Test]
    public function it_bills_several_periods_when_looking_further_ahead(): void
    {
        [$membership, $gym] = $this->activeMembership();
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);
        $this->bookAddon($membership, $addon);

        // 70 days ahead covers the periods starting 21.07., 21.08. and 21.09.
        $this->runBilling(daysAhead: 70);

        $this->assertSame(
            ['2026-07-21', '2026-08-21', '2026-09-21'],
            $this->addonPayments($addon)->map(fn ($p) => $p->due_date->toDateString())->all()
        );
    }

    #[Test]
    public function it_bills_an_addon_booked_after_the_fee_payment_exists(): void
    {
        [$membership, $gym] = $this->activeMembership();

        // The membership fee for the current period is created first.
        $this->runBilling();

        // Only afterwards the member books the add-on.
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id, 'price' => 8.62]);
        $this->bookAddon($membership, $addon);

        $this->runBilling();

        $this->assertCount(1, $this->addonPayments($addon));
    }
}
