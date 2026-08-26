<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberAccessLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberStaticLoginCodeTest extends TestCase
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
    public function it_sets_a_static_login_code(): void
    {
        [$owner, , $member] = $this->ownerWithMember();

        $this->actingAs($owner)
            ->post(route('members.access.static-login-code.store', $member), [
                'static_login_code' => '428913',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $config = MemberAccessConfig::where('member_id', $member->id)->firstOrFail();
        $this->assertSame('428913', $config->static_login_code);
    }

    #[Test]
    public function it_replaces_an_existing_static_login_code(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        MemberAccessConfig::factory()->withStaticLoginCode('111111')->create(['member_id' => $member->id]);

        $this->actingAs($owner)
            ->post(route('members.access.static-login-code.store', $member), [
                'static_login_code' => '222222',
            ])
            ->assertRedirect();

        $config = MemberAccessConfig::where('member_id', $member->id)->firstOrFail();
        $this->assertSame('222222', $config->static_login_code);

        $log = MemberAccessLog::where('member_id', $member->id)
            ->where('action', MemberAccessLog::ACTION_STATIC_CODE_SET)
            ->firstOrFail();
        $this->assertTrue($log->metadata['replaced_existing']);
    }

    #[Test]
    public function it_rejects_codes_that_are_not_six_digits(): void
    {
        [$owner, , $member] = $this->ownerWithMember();

        foreach (['12345', '1234567', 'abcdef', ''] as $invalid) {
            $this->actingAs($owner)
                ->post(route('members.access.static-login-code.store', $member), [
                    'static_login_code' => $invalid,
                ])
                ->assertSessionHasErrors('static_login_code');
        }

        $this->assertNull(MemberAccessConfig::where('member_id', $member->id)->value('static_login_code'));
    }

    #[Test]
    public function it_removes_a_static_login_code(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        MemberAccessConfig::factory()->withStaticLoginCode('424242')->create(['member_id' => $member->id]);

        $this->actingAs($owner)
            ->delete(route('members.access.static-login-code.destroy', $member))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull(MemberAccessConfig::where('member_id', $member->id)->value('static_login_code'));
        $this->assertDatabaseHas('member_access_logs', [
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_STATIC_CODE_REMOVED,
        ]);
    }

    #[Test]
    public function removing_a_missing_static_login_code_reports_an_error(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        MemberAccessConfig::factory()->create(['member_id' => $member->id]);

        $this->actingAs($owner)
            ->delete(route('members.access.static-login-code.destroy', $member))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('member_access_logs', [
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_STATIC_CODE_REMOVED,
        ]);
    }

    #[Test]
    public function it_denies_access_to_members_of_another_gym(): void
    {
        [$owner] = $this->ownerWithMember();
        $foreignMember = Member::factory()->create(['gym_id' => Gym::factory()->create()->id]);

        $this->actingAs($owner)
            ->post(route('members.access.static-login-code.store', $foreignMember), [
                'static_login_code' => '428913',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('member_access_configs', ['member_id' => $foreignMember->id]);
    }

    #[Test]
    public function the_code_is_never_serialised_to_the_frontend(): void
    {
        [, , $member] = $this->ownerWithMember();
        $config = MemberAccessConfig::factory()->withStaticLoginCode('424242')->create(['member_id' => $member->id]);

        $array = $config->fresh()->toArray();

        $this->assertArrayNotHasKey('static_login_code', $array);
        $this->assertTrue($array['has_static_login_code']);
    }

    #[Test]
    public function the_code_cannot_be_mass_assigned_through_the_generic_update_endpoint(): void
    {
        [$owner, , $member] = $this->ownerWithMember();

        $this->actingAs($owner)
            ->put(route('members.access.update', $member), [
                'qr_code_enabled' => true,
                'static_login_code' => '999999',
            ])
            ->assertRedirect();

        $this->assertNull(MemberAccessConfig::where('member_id', $member->id)->value('static_login_code'));
    }
}
