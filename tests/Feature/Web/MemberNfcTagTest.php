<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberAccessLog;
use App\Models\MemberNfcTag;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberNfcTagTest extends TestCase
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

    private function configWithPrimary(Member $member, string $uid = '04A1B2C3'): MemberAccessConfig
    {
        return MemberAccessConfig::create([
            'member_id' => $member->id,
            'nfc_enabled' => true,
            'nfc_uid' => $uid,
        ]);
    }

    #[Test]
    public function an_additional_tag_is_added_in_normalised_form(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $config = $this->configWithPrimary($member);

        $this->actingAs($owner)
            ->post(route('members.access.nfc-tags.store', $member), ['uid' => '04:aa:bb:cc'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(['04AABBCC'], $config->additionalNfcTags()->pluck('uid')->all());
        $this->assertDatabaseHas('member_access_logs', [
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_NFC_REGISTERED,
        ]);
    }

    #[Test]
    public function an_additional_tag_must_not_collide_with_any_existing_tag(): void
    {
        [$owner, $gym, $member] = $this->ownerWithMember();
        $this->configWithPrimary($member, '04A1B2C3');
        $other = Member::factory()->create(['gym_id' => $gym->id]);
        $this->configWithPrimary($other, '04DDEEFF')
            ->additionalNfcTags()->create(['uid' => '04112233']);

        foreach (['04A1B2C3', '04DDEEFF', '04112233'] as $uid) {
            $this->actingAs($owner)
                ->post(route('members.access.nfc-tags.store', $member), ['uid' => $uid])
                ->assertSessionHasErrors('uid');
        }

        $this->assertSame(1, MemberNfcTag::count());
    }

    #[Test]
    public function the_primary_tag_must_not_collide_with_an_additional_tag(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $this->configWithPrimary($member)
            ->additionalNfcTags()->create(['uid' => '04112233']);

        $this->actingAs($owner)
            ->put(route('members.access.update', $member), [
                'nfc_enabled' => true,
                'nfc_uid' => '04112233',
            ])
            ->assertSessionHasErrors('nfc_uid');
    }

    #[Test]
    public function removing_an_additional_tag_keeps_nfc_enabled_while_the_primary_tag_exists(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $config = $this->configWithPrimary($member);
        $tag = $config->additionalNfcTags()->create(['uid' => '04112233']);

        $this->actingAs($owner)
            ->delete(route('members.access.nfc-tags.destroy', [$member, $tag]))
            ->assertRedirect();

        $this->assertModelMissing($tag);
        $this->assertTrue($config->fresh()->nfc_enabled);
        $this->assertDatabaseHas('member_access_logs', [
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_NFC_REMOVED,
        ]);
    }

    #[Test]
    public function removing_the_primary_tag_promotes_the_oldest_additional_tag(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $config = $this->configWithPrimary($member);
        $config->additionalNfcTags()->create(['uid' => '04112233']);
        $config->additionalNfcTags()->create(['uid' => '04445566']);

        $this->actingAs($owner)
            ->put(route('members.access.update', $member), [
                'nfc_enabled' => true,
                'nfc_uid' => '',
            ])
            ->assertSessionHasNoErrors();

        $config->refresh();
        $this->assertSame('04112233', $config->nfc_uid);
        $this->assertTrue($config->nfc_enabled);
        $this->assertSame(['04445566'], $config->additionalNfcTags()->pluck('uid')->all());
    }

    #[Test]
    public function removing_the_last_tag_disables_nfc_access(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $config = $this->configWithPrimary($member);

        $this->actingAs($owner)
            ->put(route('members.access.update', $member), [
                'nfc_enabled' => true,
                'nfc_uid' => '',
            ])
            ->assertSessionHasNoErrors();

        $config->refresh();
        $this->assertNull($config->nfc_uid);
        $this->assertFalse($config->nfc_enabled);
    }

    #[Test]
    public function enabling_nfc_without_a_tag_keeps_it_enabled(): void
    {
        [$owner, , $member] = $this->ownerWithMember();

        $this->actingAs($owner)
            ->put(route('members.access.update', $member), [
                'nfc_enabled' => true,
                'nfc_uid' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($member->accessConfig()->first()->nfc_enabled);
    }

    #[Test]
    public function a_tag_of_another_member_cannot_be_removed(): void
    {
        [$owner, $gym, $member] = $this->ownerWithMember();
        $this->configWithPrimary($member);
        $other = Member::factory()->create(['gym_id' => $gym->id]);
        $foreignTag = $this->configWithPrimary($other, '04DDEEFF')
            ->additionalNfcTags()->create(['uid' => '04112233']);

        $this->actingAs($owner)
            ->delete(route('members.access.nfc-tags.destroy', [$member, $foreignTag]))
            ->assertNotFound();

        $this->assertModelExists($foreignTag);
    }

    #[Test]
    public function tags_of_a_member_in_another_gym_cannot_be_managed(): void
    {
        [$owner] = $this->ownerWithMember();
        [, , $foreignMember] = $this->ownerWithMember();
        $foreignTag = $this->configWithPrimary($foreignMember)
            ->additionalNfcTags()->create(['uid' => '04112233']);

        $this->actingAs($owner)
            ->post(route('members.access.nfc-tags.store', $foreignMember), ['uid' => '04778899'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('members.access.nfc-tags.destroy', [$foreignMember, $foreignTag]))
            ->assertForbidden();

        $this->assertSame(1, MemberNfcTag::count());
    }

    #[Test]
    public function the_member_page_delivers_the_additional_tags(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $this->configWithPrimary($member)
            ->additionalNfcTags()->create(['uid' => '04112233']);

        $this->actingAs($owner)
            ->get(route('members.show', $member))
            ->assertInertia(fn ($page) => $page
                ->has('member.access_config.additional_nfc_tags', 1)
                ->where('member.access_config.additional_nfc_tags.0.uid', '04112233')
            );
    }

    #[Test]
    public function deleting_a_member_releases_its_tags_for_other_members(): void
    {
        [$owner, $gym, $member] = $this->ownerWithMember();
        $member->update(['status' => 'inactive']);
        $config = $this->configWithPrimary($member);
        $config->additionalNfcTags()->create(['uid' => '04112233']);

        $this->actingAs($owner)
            ->delete(route('members.destroy', $member))
            ->assertRedirect(route('members.index'));

        $this->assertSoftDeleted($member);

        $config->refresh();
        $this->assertNull($config->nfc_uid);
        $this->assertFalse($config->nfc_enabled);
        $this->assertSame(0, MemberNfcTag::count());

        $this->assertDatabaseHas('member_access_logs', [
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_NFC_REMOVED,
        ]);

        // Both tags can be assigned to another member again.
        $other = Member::factory()->create(['gym_id' => $gym->id]);
        $this->configWithPrimary($other);

        $this->actingAs($owner)
            ->post(route('members.access.nfc-tags.store', $other), ['uid' => '04112233'])
            ->assertSessionHasNoErrors();
    }
}
