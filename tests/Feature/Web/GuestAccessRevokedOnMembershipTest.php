<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A guest access grants unlimited entry without any membership. As soon as a
 * membership defines the access period, that standing guest access has to go -
 * otherwise the member keeps entering long after the period has ended.
 */
class GuestAccessRevokedOnMembershipTest extends TestCase
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
     * @return array{0: User, 1: Member}
     */
    private function makeGuest(bool $guestAccess): array
    {
        $owner = User::factory()->create();
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        $member = Member::factory()->create([
            'gym_id' => $gym->id,
            'guest_access' => $guestAccess,
            'guest_access_granted_at' => $guestAccess ? now() : null,
            'guest_access_granted_by' => $guestAccess ? $owner->id : null,
        ]);

        return [$owner->fresh(), $member];
    }

    private function storeFreePeriod(User $owner, Member $member)
    {
        return $this->actingAs($owner)->post(
            route('members.memberships.store-free-period', $member->id),
            [
                'start_date' => '2026-09-08',
                'end_date' => '2026-10-08',
                'linked_membership_id' => null,
            ],
        );
    }

    #[Test]
    public function adding_a_free_period_revokes_the_guest_access(): void
    {
        [$owner, $member] = $this->makeGuest(true);

        $response = $this->storeFreePeriod($owner, $member);

        $response->assertSessionHasNoErrors();

        $member->refresh();

        $this->assertFalse($member->hasGuestAccess());
        $this->assertNull($member->guest_access_granted_at);
        $this->assertNull($member->guest_access_granted_by);
    }

    #[Test]
    public function a_member_without_guest_access_is_left_untouched(): void
    {
        [$owner, $member] = $this->makeGuest(false);

        $response = $this->storeFreePeriod($owner, $member);

        $response->assertSessionHasNoErrors();

        $this->assertFalse($member->fresh()->hasGuestAccess());
    }
}
