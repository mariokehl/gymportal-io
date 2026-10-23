<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberQuickSearchTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    /**
     * @return array{0: User, 1: Gym}
     */
    private function ownerWithGym(): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return [$owner->fresh(), $gym];
    }

    #[Test]
    public function it_finds_members_by_name_email_and_member_number_regardless_of_status(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $member = Member::factory()->create([
            'gym_id' => $gym->id,
            'first_name' => 'Lea',
            'last_name' => 'Hoffmann',
            'email' => 'lea.hoffmann@example.com',
            'member_number' => 'M024260198',
            'status' => 'overdue',
        ]);
        Member::factory()->create(['gym_id' => $gym->id, 'first_name' => 'Tim', 'last_name' => 'Wagner']);

        foreach (['hoff', 'LEA.HOFFMANN@', '260198', 'lea hoffmann'] as $query) {
            $this->actingAs($owner)
                ->getJson(route('members.quick-search', ['q' => $query]))
                ->assertOk()
                ->assertJsonCount(1, 'members')
                ->assertJsonPath('members.0.id', $member->id)
                ->assertJsonPath('members.0.name', 'Lea Hoffmann')
                ->assertJsonPath('members.0.status', 'overdue');
        }
    }

    #[Test]
    public function it_never_returns_members_of_another_gym(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        [, $foreignGym] = $this->ownerWithGym();

        $foreign = Member::factory()->create(['gym_id' => $foreignGym->id, 'last_name' => 'Fremdling']);

        $this->actingAs($owner)
            ->getJson(route('members.quick-search', ['q' => 'Fremdling']))
            ->assertOk()
            ->assertJsonCount(0, 'members');

        $this->actingAs($owner)
            ->getJson(route('members.quick-search', ['ids' => [$foreign->id]]))
            ->assertOk()
            ->assertJsonCount(0, 'members');
    }

    #[Test]
    public function it_resolves_recently_viewed_members_in_the_given_order(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        $first = Member::factory()->create(['gym_id' => $gym->id]);
        $second = Member::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($owner)
            ->getJson(route('members.quick-search', ['ids' => [$second->id, 999999, $first->id]]))
            ->assertOk()
            ->assertJsonCount(2, 'members')
            ->assertJsonPath('members.0.id', $second->id)
            ->assertJsonPath('members.1.id', $first->id);
    }

    #[Test]
    public function it_treats_like_wildcards_in_the_query_literally(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        Member::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($owner)
            ->getJson(route('members.quick-search', ['q' => '%']))
            ->assertOk()
            ->assertJsonCount(0, 'members');
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson(route('members.quick-search', ['q' => 'test']))
            ->assertUnauthorized();
    }
}
