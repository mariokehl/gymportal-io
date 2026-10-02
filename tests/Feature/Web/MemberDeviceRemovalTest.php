<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessLog;
use App\Models\MemberDevice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberDeviceRemovalTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym, 2: Member}
     */
    private function ownerWithMember(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        return [$owner->fresh(), $gym, $member];
    }

    #[Test]
    public function removing_a_device_revokes_all_tokens_of_the_member(): void
    {
        [$owner, $gym, $member] = $this->ownerWithMember();
        $device = MemberDevice::factory()->create(['member_id' => $member->id]);
        $member->createToken('member-pwa-full', ['member-pwa', 'full']);
        $member->createToken('member-pwa-anonymous', ['member-pwa', 'anonymous']);

        $otherMember = Member::factory()->create(['gym_id' => $gym->id]);
        $otherMember->createToken('member-pwa-full', ['member-pwa', 'full']);

        $this->actingAs($owner)
            ->delete(route('members.access.remove-device', ['member' => $member, 'device' => $device]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertModelMissing($device);
        $this->assertSame(0, $member->tokens()->count());
        $this->assertSame(1, $otherMember->tokens()->count());

        $log = MemberAccessLog::where('member_id', $member->id)
            ->where('action', 'device_removed')
            ->firstOrFail();
        $this->assertSame(2, $log->metadata['revoked_tokens']);
    }

    #[Test]
    public function a_device_of_another_member_does_not_revoke_any_tokens(): void
    {
        [$owner, $gym, $member] = $this->ownerWithMember();
        $member->createToken('member-pwa-full', ['member-pwa', 'full']);

        $otherMember = Member::factory()->create(['gym_id' => $gym->id]);
        $foreignDevice = MemberDevice::factory()->create(['member_id' => $otherMember->id]);

        $this->actingAs($owner)
            ->delete(route('members.access.remove-device', ['member' => $member, 'device' => $foreignDevice]))
            ->assertNotFound();

        $this->assertModelExists($foreignDevice);
        $this->assertSame(1, $member->tokens()->count());
    }
}
