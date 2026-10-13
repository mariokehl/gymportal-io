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
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Add-ons booked with a contract are part of it, so withdrawing the contract
 * has to end them as well.
 */
class MembershipWithdrawalAddonTest extends TestCase
{
    use RefreshDatabase;

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
    private function makeWithdrawableContract(): array
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

    private function withdraw(User $owner, Member $member, Membership $membership)
    {
        return $this->actingAs($owner)->put(
            route('members.memberships.withdraw', [
                'member' => $member->id,
                'membership' => $membership->id,
            ]),
            [],
        );
    }

    #[Test]
    public function withdrawing_a_contract_ends_all_booked_addons(): void
    {
        [$owner, $member, $membership, $gym] = $this->makeWithdrawableContract();

        $recurring = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id]);
        $included = Addon::factory()->create(['gym_id' => $gym->id]);

        $membership->addons()->attach($recurring->id, ['mode' => 'optional', 'price' => 8.62]);
        $membership->addons()->attach($included->id, ['mode' => 'included', 'price' => 0]);

        $this->assertSame(2, $membership->activeAddons()->count());

        $this->withdraw($owner, $member, $membership)->assertSessionHasNoErrors();

        // Still usable on the withdrawal date, gone the day after
        $this->assertSame(2, $membership->activeAddons()->count());

        Carbon::setTestNow('2026-09-09 10:00:00');

        $this->assertSame(0, $membership->activeAddons()->count());

        foreach ($membership->addons()->get() as $addon) {
            $this->assertNotNull($addon->pivot->cancelled_at);
            $this->assertSame('2026-09-08', Carbon::parse($addon->pivot->cancellation_effective_at)->toDateString());
        }
    }

    #[Test]
    public function an_addon_cancelled_earlier_keeps_its_cancellation_date_but_ends_today(): void
    {
        [$owner, $member, $membership, $gym] = $this->makeWithdrawableContract();

        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id]);

        $membership->addons()->attach($addon->id, [
            'mode' => 'optional',
            'price' => 8.62,
            'cancelled_at' => '2026-09-03 12:00:00',
            'cancellation_effective_at' => '2026-09-30',
        ]);

        $this->withdraw($owner, $member, $membership)->assertSessionHasNoErrors();

        $pivot = $membership->addons()->first()->pivot;

        $this->assertSame('2026-09-03 12:00:00', Carbon::parse($pivot->cancelled_at)->toDateTimeString());
        $this->assertSame('2026-09-08', Carbon::parse($pivot->cancellation_effective_at)->toDateString());
    }

    #[Test]
    public function an_addon_that_already_ended_keeps_its_end_date(): void
    {
        [$owner, $member, $membership, $gym] = $this->makeWithdrawableContract();

        $addon = Addon::factory()->usageFlatRate()->create(['gym_id' => $gym->id]);

        $membership->addons()->attach($addon->id, [
            'mode' => 'optional',
            'price' => 8.62,
            'cancelled_at' => '2026-09-01 12:00:00',
            'cancellation_effective_at' => '2026-09-05',
        ]);

        $this->withdraw($owner, $member, $membership)->assertSessionHasNoErrors();

        $pivot = $membership->addons()->first()->pivot;

        $this->assertSame('2026-09-05', Carbon::parse($pivot->cancellation_effective_at)->toDateString());
    }
}
