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
