<?php

namespace Tests\Feature\Web;

use App\Enums\Aggregator;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberAccessLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberAggregatorTest extends TestCase
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

    private function assignAggregator(Member $member, Aggregator $aggregator, string $accountId, bool $linked = false): MemberAccessConfig
    {
        $config = MemberAccessConfig::factory()->create(['member_id' => $member->id]);
        $config->aggregator = $aggregator;
        $config->aggregator_account_id = $accountId;
        $config->aggregator_linked_at = $linked ? now() : null;
        $config->save();

        return $config;
    }

    #[Test]
    public function it_stores_an_aggregator_account_as_pending(): void
    {
        [$owner, , $member] = $this->ownerWithMember();

        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $member), [
                'aggregator' => 'wellpass',
                'aggregator_account_id' => '  WP-48213977 ',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $config = MemberAccessConfig::where('member_id', $member->id)->firstOrFail();
        $this->assertSame(Aggregator::Wellpass, $config->aggregator);
        $this->assertSame('WP-48213977', $config->aggregator_account_id);
        $this->assertSame(MemberAccessConfig::AGGREGATOR_STATUS_PENDING, $config->aggregator_status);
        $this->assertDatabaseHas('member_access_logs', [
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_AGGREGATOR_SET,
        ]);
    }

    #[Test]
    public function changing_the_account_id_resets_the_link_to_pending(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $this->assignAggregator($member, Aggregator::Hansefit, 'HF-1', linked: true);

        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $member), [
                'aggregator' => 'hansefit',
                'aggregator_account_id' => 'HF-2',
            ])
            ->assertRedirect();

        $this->assertSame(
            MemberAccessConfig::AGGREGATOR_STATUS_PENDING,
            MemberAccessConfig::where('member_id', $member->id)->firstOrFail()->aggregator_status
        );
    }

    #[Test]
    public function saving_unchanged_values_keeps_an_existing_link(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $this->assignAggregator($member, Aggregator::Hansefit, 'HF-1', linked: true);

        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $member), [
                'aggregator' => 'hansefit',
                'aggregator_account_id' => 'HF-1',
            ])
            ->assertRedirect();

        $this->assertSame(
            MemberAccessConfig::AGGREGATOR_STATUS_LINKED,
            MemberAccessConfig::where('member_id', $member->id)->firstOrFail()->aggregator_status
        );
    }

    #[Test]
    public function it_rejects_unknown_providers_and_invalid_account_ids(): void
    {
        [$owner, , $member] = $this->ownerWithMember();

        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $member), [
                'aggregator' => 'unknown',
                'aggregator_account_id' => 'X-1',
            ])
            ->assertSessionHasErrors('aggregator');

        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $member), [
                'aggregator' => 'usc',
                'aggregator_account_id' => '<script>',
            ])
            ->assertSessionHasErrors('aggregator_account_id');

        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $member), [
                'aggregator' => 'usc',
                'aggregator_account_id' => '   ',
            ])
            ->assertSessionHasErrors('aggregator_account_id');
    }

    #[Test]
    public function an_account_id_is_unique_per_provider_within_a_gym(): void
    {
        [$owner, $gym, $member] = $this->ownerWithMember();
        $other = Member::factory()->create(['gym_id' => $gym->id]);
        $this->assignAggregator($other, Aggregator::Wellpass, 'WP-1');

        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $member), [
                'aggregator' => 'wellpass',
                'aggregator_account_id' => 'WP-1',
            ])
            ->assertSessionHasErrors('aggregator_account_id');

        // The same id at another provider is a different account.
        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $member), [
                'aggregator' => 'hansefit',
                'aggregator_account_id' => 'WP-1',
            ])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function the_same_account_id_may_exist_in_another_gym(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $foreignMember = Member::factory()->create(['gym_id' => Gym::factory()->create()->id]);
        $this->assignAggregator($foreignMember, Aggregator::Wellpass, 'WP-1');

        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $member), [
                'aggregator' => 'wellpass',
                'aggregator_account_id' => 'WP-1',
            ])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function it_removes_an_aggregator_account(): void
    {
        [$owner, , $member] = $this->ownerWithMember();
        $this->assignAggregator($member, Aggregator::Wellhub, 'WH-1', linked: true);

        $this->actingAs($owner)
            ->delete(route('members.access.aggregator.destroy', $member))
            ->assertRedirect()
            ->assertSessionHas('success');

        $config = MemberAccessConfig::where('member_id', $member->id)->firstOrFail();
        $this->assertNull($config->aggregator);
        $this->assertNull($config->aggregator_account_id);
        $this->assertNull($config->aggregator_linked_at);
        $this->assertDatabaseHas('member_access_logs', [
            'member_id' => $member->id,
            'action' => MemberAccessLog::ACTION_AGGREGATOR_REMOVED,
        ]);
    }

    #[Test]
    public function removing_a_missing_aggregator_reports_an_error(): void
    {
        [$owner, , $member] = $this->ownerWithMember();

        $this->actingAs($owner)
            ->delete(route('members.access.aggregator.destroy', $member))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    #[Test]
    public function it_denies_access_to_members_of_another_gym(): void
    {
        [$owner] = $this->ownerWithMember();
        $foreignMember = Member::factory()->create(['gym_id' => Gym::factory()->create()->id]);
        $this->assignAggregator($foreignMember, Aggregator::Wellpass, 'WP-9');

        $this->actingAs($owner)
            ->put(route('members.access.aggregator.update', $foreignMember), [
                'aggregator' => 'hansefit',
                'aggregator_account_id' => 'HF-1',
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('members.access.aggregator.destroy', $foreignMember))
            ->assertForbidden();

        $this->assertSame(
            Aggregator::Wellpass,
            MemberAccessConfig::where('member_id', $foreignMember->id)->firstOrFail()->aggregator
        );
    }

    #[Test]
    public function the_generic_access_update_cannot_set_the_aggregator(): void
    {
        [$owner, , $member] = $this->ownerWithMember();

        $this->actingAs($owner)
            ->put(route('members.access.update', $member), [
                'qr_code_enabled' => true,
                'aggregator' => 'wellpass',
                'aggregator_account_id' => 'WP-1',
            ])
            ->assertRedirect();

        $this->assertNull(MemberAccessConfig::where('member_id', $member->id)->value('aggregator'));
    }

    #[Test]
    public function the_member_list_filters_by_aggregator_and_exposes_only_the_badge(): void
    {
        [$owner, $gym, $wellpassMember] = $this->ownerWithMember();
        $hansefitMember = Member::factory()->create(['gym_id' => $gym->id]);
        $plainMember = Member::factory()->create(['gym_id' => $gym->id]);
        $this->assignAggregator($wellpassMember, Aggregator::Wellpass, 'WP-1', linked: true);
        $this->assignAggregator($hansefitMember, Aggregator::Hansefit, 'HF-1');

        $idsFor = function (string $filter) use ($owner) {
            $ids = null;
            $this->actingAs($owner)
                ->get(route('members.index', ['aggregator' => $filter]))
                ->assertInertia(function (AssertableInertia $page) use (&$ids) {
                    $ids = collect($page->toArray()['props']['members']['data'])->pluck('id')->sort()->values()->all();
                });

            return $ids;
        };

        $this->assertSame([$wellpassMember->id], $idsFor('wellpass'));
        $this->assertEqualsCanonicalizing([$wellpassMember->id, $hansefitMember->id], $idsFor('any'));
        $this->assertSame([$plainMember->id], $idsFor('none'));
        $this->assertCount(3, $idsFor(''));

        $this->actingAs($owner)
            ->get(route('members.index', ['aggregator' => 'wellpass']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('members.data.0.aggregator', [
                    'key' => 'wellpass',
                    'name' => 'EGYM Wellpass',
                    'status' => MemberAccessConfig::AGGREGATOR_STATUS_LINKED,
                ])
                ->missing('members.data.0.access_config')
            );
    }
}
