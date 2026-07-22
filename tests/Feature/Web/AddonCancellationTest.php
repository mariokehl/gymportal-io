<?php

namespace Tests\Feature\Web;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
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

    private function cancel(User $owner, Member $member, Membership $membership, Addon $addon)
    {
        return $this->actingAs($owner)
            ->put(route('members.memberships.addons.toggle-cancellation', [
                'member' => $member,
                'membership' => $membership,
                'addon' => $addon,
            ]));
    }

    #[Test]
    public function it_cancels_a_recurring_addon_to_the_end_of_the_billing_period(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        $this->cancel($owner, $member, $membership, $addon)->assertRedirect();

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertNotNull($pivot->cancelled_at);
        $this->assertSame($owner->id, $pivot->cancelled_by);

        // The contract started on the 21st, so the period runs to the 20th of
        // the following month — not to the end of the calendar month.
        $this->assertSame('2026-08-20', Carbon::parse($pivot->cancellation_effective_at)->toDateString());
    }

    #[Test]
    public function it_cancels_to_the_end_of_the_calendar_month_for_a_contract_starting_on_the_first(): void
    {
        [$owner, $member, $membership, $addon] = $this->bookedAddonScenario();

        // Mirrors "Verträge immer zum 1. des Monats starten".
        $membership->update(['start_date' => '2026-07-01']);

        $this->cancel($owner, $member, $membership->fresh(), $addon)->assertRedirect();

        $pivot = $membership->addons()->find($addon->id)->pivot;

        $this->assertSame('2026-07-31', Carbon::parse($pivot->cancellation_effective_at)->toDateString());
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
