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
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Add-ons booked with a contract are part of it, so cancelling the contract
 * cancels them to the same date — and revoking the cancellation brings them
 * back.
 */
class MembershipCancellationAddonTest extends TestCase
{
    use RefreshDatabase;

    private const CONTRACT_END = '2026-09-30';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Role::factory()->create(['name' => 'Administrator', 'slug' => 'admin']);
        Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner']);

        Carbon::setTestNow('2026-09-08 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Member, 2: Membership, 3: Gym}
     */
    private function makeCancellableContract(): array
    {
        $owner = User::factory()->create();
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'is_free_trial_plan' => false,
        ]);

        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
        ]);

        return [$owner->fresh(), $member, $membership, $gym];
    }

    private function cancel(User $owner, Member $member, Membership $membership, array $payload = [])
    {
        return $this->actingAs($owner)->put(
            route('members.memberships.cancel', [
                'member' => $member->id,
                'membership' => $membership->id,
            ]),
            array_merge([
                'cancellation_date' => self::CONTRACT_END,
                'cancellation_reason' => 'other',
                'cancellation_type' => 'extraordinary',
            ], $payload),
        );
    }

    private function revokeCancellation(User $owner, Member $member, Membership $membership)
    {
        return $this->actingAs($owner)->put(
            route('members.memberships.revoke-cancellation', [
                'member' => $member->id,
                'membership' => $membership->id,
            ]),
        );
    }

    private function bookRecurringAddon(Membership $membership, Gym $gym, array $pivot = []): Addon
    {
        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id]);

        $membership->addons()->attach($addon->id, array_merge(['mode' => 'optional', 'price' => 8.62], $pivot));

        return $addon;
    }

    private function pivotOf(Membership $membership, Addon $addon): object
    {
        return $membership->addons()->where('addons.id', $addon->id)->first()->pivot;
    }

    private function effectiveDateOf(Membership $membership, Addon $addon): ?string
    {
        $effectiveAt = $this->pivotOf($membership, $addon)->cancellation_effective_at;

        return $effectiveAt ? Carbon::parse($effectiveAt)->toDateString() : null;
    }

    #[Test]
    public function cancelling_a_contract_cancels_its_recurring_addons_to_the_same_date(): void
    {
        [$owner, $member, $membership, $gym] = $this->makeCancellableContract();

        $recurring = $this->bookRecurringAddon($membership, $gym);
        $oneTime = Addon::factory()->create(['gym_id' => $gym->id]);
        $membership->addons()->attach($oneTime->id, ['mode' => 'optional', 'price' => 65]);

        $this->cancel($owner, $member, $membership)->assertSessionHasNoErrors();

        $pivot = $this->pivotOf($membership, $recurring);
        $this->assertNotNull($pivot->cancelled_at);
        $this->assertSame(self::CONTRACT_END, $this->effectiveDateOf($membership, $recurring));
        $this->assertSame($owner->id, $pivot->cancelled_by);
        $this->assertTrue((bool) $pivot->cancelled_with_membership);

        // A one-time add-on is already billed and has no term to end
        $this->assertNull($this->pivotOf($membership, $oneTime)->cancelled_at);
    }

    #[Test]
    public function an_immediate_cancellation_ends_the_addons_today(): void
    {
        [$owner, $member, $membership, $gym] = $this->makeCancellableContract();

        $addon = $this->bookRecurringAddon($membership, $gym);

        $this->cancel($owner, $member, $membership, [
            'cancellation_date' => '2026-09-08',
            'immediate' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-09-08', $this->effectiveDateOf($membership, $addon));
    }

    #[Test]
    public function an_addon_cancelled_earlier_is_only_shortened_to_the_contract_end(): void
    {
        [$owner, $member, $membership, $gym] = $this->makeCancellableContract();

        $endsLater = $this->bookRecurringAddon($membership, $gym, [
            'cancelled_at' => '2026-09-03 12:00:00',
            'cancellation_effective_at' => '2026-10-31',
        ]);
        $endsEarlier = $this->bookRecurringAddon($membership, $gym, [
            'cancelled_at' => '2026-09-03 12:00:00',
            'cancellation_effective_at' => '2026-09-15',
        ]);

        $this->cancel($owner, $member, $membership)->assertSessionHasNoErrors();

        $shortened = $this->pivotOf($membership, $endsLater);
        $this->assertSame('2026-09-03 12:00:00', Carbon::parse($shortened->cancelled_at)->toDateTimeString());
        $this->assertSame(self::CONTRACT_END, $this->effectiveDateOf($membership, $endsLater));

        $untouched = $this->pivotOf($membership, $endsEarlier);
        $this->assertSame('2026-09-15', $this->effectiveDateOf($membership, $endsEarlier));
        $this->assertFalse((bool) $untouched->cancelled_with_membership);
    }

    #[Test]
    public function scheduled_addon_payments_after_the_contract_end_are_cancelled(): void
    {
        [$owner, $member, $membership, $gym] = $this->makeCancellableContract();

        $addon = $this->bookRecurringAddon($membership, $gym);

        $makePayment = fn (string $dueDate) => Payment::create([
            'gym_id' => $gym->id,
            'membership_id' => $membership->id,
            'member_id' => $member->id,
            'amount' => 8.62,
            'currency' => 'EUR',
            'description' => "Add-on: {$addon->name}",
            'status' => 'pending',
            'due_date' => $dueDate,
            'metadata' => ['payment_type' => 'addon_recurring', 'addon_id' => $addon->id],
        ]);

        $withinTerm = $makePayment('2026-09-15');
        $afterTerm = $makePayment('2026-10-01');

        $this->cancel($owner, $member, $membership)->assertSessionHasNoErrors();

        $this->assertSame('pending', $withinTerm->fresh()->status);
        $this->assertSame('canceled', $afterTerm->fresh()->status);
    }

    #[Test]
    public function revoking_the_cancellation_restores_the_addons(): void
    {
        [$owner, $member, $membership, $gym] = $this->makeCancellableContract();

        $running = $this->bookRecurringAddon($membership, $gym);
        $cancelledOnItsOwn = $this->bookRecurringAddon($membership, $gym, [
            'cancelled_at' => '2026-09-03 12:00:00',
            'cancellation_effective_at' => '2026-10-31',
            'cancelled_by' => $owner->id,
        ]);

        $this->cancel($owner, $member, $membership)->assertSessionHasNoErrors();
        $this->revokeCancellation($owner, $member, $membership)->assertSessionHasNoErrors();

        $restored = $this->pivotOf($membership, $running);
        $this->assertNull($restored->cancelled_at);
        $this->assertNull($restored->cancellation_effective_at);
        $this->assertNull($restored->cancelled_by);
        $this->assertFalse((bool) $restored->cancelled_with_membership);

        // Its own earlier cancellation stays in place with its own date
        $ownCancellation = $this->pivotOf($membership, $cancelledOnItsOwn);
        $this->assertSame('2026-09-03 12:00:00', Carbon::parse($ownCancellation->cancelled_at)->toDateTimeString());
        $this->assertSame('2026-10-31', $this->effectiveDateOf($membership, $cancelledOnItsOwn));
        $this->assertFalse((bool) $ownCancellation->cancelled_with_membership);
        $this->assertNull($ownCancellation->original_cancellation_effective_at);
    }

    #[Test]
    public function an_addon_cancelled_with_the_contract_cannot_be_revoked_on_its_own(): void
    {
        [$owner, $member, $membership, $gym] = $this->makeCancellableContract();

        $addon = $this->bookRecurringAddon($membership, $gym);

        $this->cancel($owner, $member, $membership)->assertSessionHasNoErrors();

        $this->actingAs($owner)->put(
            route('members.memberships.addons.toggle-cancellation', [
                'member' => $member->id,
                'membership' => $membership->id,
                'addon' => $addon->id,
            ]),
        )->assertSessionHasErrors('error');

        $this->assertSame(self::CONTRACT_END, $this->effectiveDateOf($membership, $addon));
    }
}
