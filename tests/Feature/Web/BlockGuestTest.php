<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberBlocklist;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guests are blocked through the blocklist like members and return to
 * "extern" when unblocked, not to "active".
 */
class BlockGuestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $this->owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $this->owner->fresh();
    }

    private function guest(): Member
    {
        return Member::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => 'extern',
            'registration_source' => 'guest',
        ]);
    }

    private function block(Member $member): MemberBlocklist
    {
        $this->actingAs($this->owner)
            ->post(route('blocklist.block-member', $member), [
                'reason' => 'manual',
                'notes' => 'Ausweis eines Dritten vorgezeigt',
            ])
            ->assertRedirect();

        return MemberBlocklist::where('original_member_id', $member->id)->sole();
    }

    private function unblock(MemberBlocklist $entry): void
    {
        $this->actingAs($this->owner)
            ->post(route('blocklist.unblock', $entry), [
                'unblock_reason' => 'Sachverhalt geklärt',
            ])
            ->assertRedirect();
    }

    #[Test]
    public function a_guest_can_be_blocked(): void
    {
        $guest = $this->guest();

        $this->block($guest);

        $this->assertSame('blocked', $guest->fresh()->status);
    }

    #[Test]
    public function an_unblocked_guest_returns_to_extern(): void
    {
        $guest = $this->guest();

        $this->unblock($this->block($guest));

        $this->assertSame('extern', $guest->fresh()->status);
    }

    #[Test]
    public function a_former_guest_with_a_membership_returns_to_active(): void
    {
        $member = $this->guest();
        Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => MembershipPlan::factory()->create(['gym_id' => $this->gym->id])->id,
            'status' => 'active',
        ]);

        $this->unblock($this->block($member));

        $this->assertSame('active', $member->fresh()->status);
    }
}
