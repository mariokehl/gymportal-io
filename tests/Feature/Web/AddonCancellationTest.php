<?php

namespace Tests\Feature\Web;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AddonCancellationTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-15 10:00:00');

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Book an add-on for a membership. Defaults to a recurring service.
     *
     * @return array{0: User, 1: Member, 2: Membership, 3: Addon}
     */
    private function bookedAddonScenario(bool $recurring = true): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            // Billing is anchored to the start date, not to calendar months.
            'start_date' => '2026-07-21',
        ]);

        $factory = $recurring ? Addon::factory()->usageFlatRate() : Addon::factory();
        $addon = $factory->create(['gym_id' => $gym->id, 'price' => 8.62]);

        $membership->addons()->attach($addon->id, ['mode' => 'optional', 'price' => 8.62]);

        return [$owner->fresh(), $member, $membership, $addon];
    }

    private function cancel(
        User $owner,
        Member $member,
        Membership $membership,
        Addon $addon,
        ?string $effectiveAt = '2026-07-31'
    ) {
        return $this->actingAs($owner)
            ->put(route('members.memberships.addons.toggle-cancellation', [
                'member' => $member,
                'membership' => $membership,
                'addon' => $addon,
            ]), $effectiveAt === null ? [] : ['cancellation_effective_at' => $effectiveAt]);
    }

    #[Test]
    public function it_cancels_a_recurring_addon_to_the_submitted_date(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        $this->cancel($owner, $member, $membership, $addon, '2026-08-20')->assertRedirect();

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertNotNull($pivot->cancelled_at);
        $this->assertSame($owner->id, $pivot->cancelled_by);
        $this->assertSame('2026-08-20', Carbon::parse($pivot->cancellation_effective_at)->toDateString());
    }

    #[Test]
    public function it_accepts_a_date_that_ignores_the_billing_period(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        // The contract started on the 21st, so its billing period runs to the
        // 20th — the owner may still cancel to the end of the calendar month.
        $this->cancel($owner, $member, $membership, $addon, '2026-07-31')->assertRedirect();

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertSame('2026-07-31', Carbon::parse($pivot->cancellation_effective_at)->toDateString());
    }

    #[Test]
    public function it_cancels_to_today(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        $this->cancel($owner, $member, $membership, $addon, '2026-07-15')->assertRedirect();

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertSame('2026-07-15', Carbon::parse($pivot->cancellation_effective_at)->toDateString());
    }

    #[Test]
    public function it_rejects_a_cancellation_date_in_the_past(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        $this->cancel($owner, $member, $membership, $addon, '2026-07-14')
            ->assertSessionHasErrors('cancellation_effective_at');

        $this->assertNull($membership->addons()->find($addon->id)->pivot->cancelled_at);
    }

    #[Test]
    public function it_requires_a_cancellation_date(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        $this->cancel($owner, $member, $membership, $addon, effectiveAt: null)
            ->assertSessionHasErrors('cancellation_effective_at');

        $this->assertNull($membership->addons()->find($addon->id)->pivot->cancelled_at);
    }

    #[Test]
    public function it_revokes_a_pending_cancellation(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        $this->cancel($owner, $member, $membership, $addon);
        $this->cancel($owner, $member, $membership, $addon)->assertRedirect();

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertNull($pivot->cancelled_at);
        $this->assertNull($pivot->cancellation_effective_at);
        $this->assertNull($pivot->cancelled_by);
    }

    /**
     * Create a scheduled add-on charge as the billing run writes it.
     */
    private function scheduledAddonPayment(
        Membership $membership,
        Addon $addon,
        string $dueDate,
        array $attributes = []
    ): Payment {
        return Payment::create(array_merge([
            'gym_id' => $membership->member->gym_id,
            'membership_id' => $membership->id,
            'member_id' => $membership->member_id,
            'amount' => 8.62,
            'currency' => 'EUR',
            'description' => "Add-on: {$addon->name}",
            'status' => 'pending',
            'due_date' => $dueDate,
            'metadata' => ['payment_type' => 'addon_recurring', 'addon_id' => $addon->id],
        ], $attributes));
    }

    #[Test]
    public function it_cancels_scheduled_payments_after_the_cancellation_date(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        $withinTerm = $this->scheduledAddonPayment($membership, $addon, '2026-07-21');
        $afterTerm = $this->scheduledAddonPayment($membership, $addon, '2026-08-21');

        $this->cancel($owner, $member, $membership, $addon, '2026-07-31')->assertRedirect();

        // The charge for the period the member still uses stays untouched.
        $this->assertSame('pending', $withinTerm->fresh()->status);

        $cancelled = $afterTerm->fresh();
        $this->assertSame('canceled', $cancelled->status);
        $this->assertNotNull($cancelled->canceled_at);
        $this->assertSame('addon_cancelled', $cancelled->metadata['canceled_reason']);
    }

    #[Test]
    public function it_cancels_all_scheduled_payments_when_a_trial_addon_is_dropped(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        // Mirrors the reported case: a trial runs 21.–30.09. and nothing may be
        // billed from 01.10. on.
        Carbon::setTestNow('2026-09-25 10:00:00');
        $membership->update(['start_date' => '2026-09-21']);

        $october = $this->scheduledAddonPayment($membership, $addon, '2026-10-21');
        $november = $this->scheduledAddonPayment($membership, $addon, '2026-11-21');

        $this->cancel($owner, $member, $membership->fresh(), $addon, '2026-09-30')->assertRedirect();

        $this->assertSame('canceled', $october->fresh()->status);
        $this->assertSame('canceled', $november->fresh()->status);
    }

    #[Test]
    public function it_keeps_payments_that_were_already_handed_to_the_provider(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        $atProvider = $this->scheduledAddonPayment($membership, $addon, '2026-08-21', [
            'mollie_payment_id' => 'tr_test123',
        ]);
        $booked = $this->scheduledAddonPayment($membership, $addon, '2026-08-21', [
            'transaction_id' => 'txn_test123',
        ]);
        $paid = $this->scheduledAddonPayment($membership, $addon, '2026-08-21', [
            'status' => 'paid',
        ]);

        $this->cancel($owner, $member, $membership, $addon, '2026-07-31')->assertRedirect();

        $this->assertSame('pending', $atProvider->fresh()->status);
        $this->assertSame('pending', $booked->fresh()->status);
        $this->assertSame('paid', $paid->fresh()->status);
    }

    #[Test]
    public function it_leaves_payments_of_another_addon_untouched(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        $otherAddon = Addon::factory()->usageFlatRate()->create([
            'gym_id' => $membership->membershipPlan->gym_id,
        ]);
        $otherPayment = $this->scheduledAddonPayment($membership, $otherAddon, '2026-08-21');

        $this->cancel($owner, $member, $membership, $addon, '2026-07-31')->assertRedirect();

        $this->assertSame('pending', $otherPayment->fresh()->status);
    }

    #[Test]
    public function it_rejects_cancelling_a_one_time_addon(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario(recurring: false);

        $this->cancel($owner, $member, $membership, $addon)
            ->assertSessionHasErrors('error');

        $this->assertNull($membership->addons()->find($addon->id)->pivot->cancelled_at);
    }

    #[Test]
    public function it_rejects_an_addon_not_booked_for_the_membership(): void
    {
        [$owner, $member, $membership] = $this->bookedAddonScenario();

        $otherAddon = Addon::factory()->recurring()->create([
            'gym_id' => $membership->membershipPlan->gym_id,
        ]);

        $this->cancel($owner, $member, $membership, $otherAddon)
            ->assertSessionHasErrors('error');
    }

    #[Test]
    public function it_forbids_cancelling_for_a_membership_of_another_member(): void
    {
        [$owner, , $membership, $addon] = $this->bookedAddonScenario();

        $otherMember = Member::factory()->create(['gym_id' => $owner->current_gym_id]);

        $this->cancel($owner, $otherMember, $membership, $addon)->assertForbidden();
    }

    #[Test]
    public function it_forbids_cancelling_an_addon_of_another_gym(): void
    {
        [, $member, $membership, $addon] = $this->bookedAddonScenario();

        $stranger = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $strangerGym = Gym::factory()->create(['owner_id' => $stranger->id]);
        $stranger->update(['current_gym_id' => $strangerGym->id]);

        $this->cancel($stranger->fresh(), $member, $membership, $addon)->assertForbidden();
    }
}
