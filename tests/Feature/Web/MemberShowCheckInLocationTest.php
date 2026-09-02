<?php

namespace Tests\Feature\Web;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberShowCheckInLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;

        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $this->owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $this->owner->fresh();

        $this->member = Member::factory()->create(['gym_id' => $this->gym->id]);
    }

    #[Test]
    public function it_exposes_the_visited_location_of_a_check_in(): void
    {
        $checkIn = CheckIn::factory()->create([
            'member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('members.show', $this->member))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Members/Show')
                ->has('member.check_ins', 1)
                ->where('member.check_ins.0.id', $checkIn->id)
                ->where('member.check_ins.0.gym_id', $this->gym->id)
                ->where('member.check_ins.0.gym.name', $this->gym->name)
            );
    }

    #[Test]
    public function it_counts_every_check_in_for_the_tab_badge(): void
    {
        // More than the detail page lists, so the badge cannot simply count the
        // check-ins it received.
        CheckIn::factory()->count(12)->create([
            'member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('members.show', $this->member))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Members/Show')
                ->has('member.check_ins', 10)
                ->where('checkInsTotal', 12)
            );
    }

    #[Test]
    public function it_paginates_the_check_in_history(): void
    {
        // Distinct times, so the pages are ordered deterministically.
        foreach (range(1, 12) as $offset) {
            CheckIn::factory()->create([
                'member_id' => $this->member->id,
                'gym_id' => $this->gym->id,
                'check_in_time' => now()->subHours($offset),
            ]);
        }

        $response = $this->actingAs($this->owner)
            ->getJson(route('members.check-ins', ['member' => $this->member, 'page' => 2]))
            ->assertOk();

        // 12 check-ins, 10 per page: the second page holds the remaining two.
        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('total', 12)
            ->assertJsonPath('has_more', false);

        // The rows carry the same shape the page renders initially.
        $this->assertArrayHasKey('check_in_method_text', $response->json('data.0'));
        $this->assertArrayHasKey('gym', $response->json('data.0'));
    }

    #[Test]
    public function it_reports_more_pages_while_they_remain(): void
    {
        CheckIn::factory()->count(25)->create([
            'member_id' => $this->member->id,
            'gym_id' => $this->gym->id,
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('members.check-ins', $this->member))
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('last_page', 3);
    }

    #[Test]
    public function it_denies_the_check_in_history_of_a_member_of_another_gym(): void
    {
        $stranger = Member::factory()->create([
            'gym_id' => Gym::factory()->create()->id,
        ]);

        CheckIn::factory()->create([
            'member_id' => $stranger->id,
            'gym_id' => $stranger->gym_id,
        ]);

        $this->actingAs($this->owner)
            ->getJson(route('members.check-ins', $stranger))
            ->assertForbidden();
    }

    #[Test]
    public function it_names_the_other_location_for_a_cross_location_check_in(): void
    {
        $otherGym = Gym::factory()->create([
            'owner_id' => $this->owner->id,
            'name' => 'Studio Nord',
            'display_name' => 'gymportal Nord',
        ]);

        CheckIn::factory()->create([
            'member_id' => $this->member->id,
            'gym_id' => $otherGym->id,
        ]);

        $this->actingAs($this->owner)
            ->get(route('members.show', $this->member))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Members/Show')
                ->has('member.check_ins', 1)
                ->where('member.check_ins.0.gym_id', $otherGym->id)
                ->where('member.check_ins.0.gym.display_name', 'gymportal Nord')
                ->where('member.gym_id', $this->gym->id)
            );
    }
}
