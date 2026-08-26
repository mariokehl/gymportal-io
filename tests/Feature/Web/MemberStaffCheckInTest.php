<?php

namespace Tests\Feature\Web;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Role;
use App\Models\ScannerAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberStaffCheckInTest extends TestCase
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
    private function ownerGymMember(array $memberAttributes = []): array
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);
        $member = Member::factory()->create($memberAttributes + ['gym_id' => $gym->id]);

        return [$owner->fresh(), $gym, $member];
    }

    #[Test]
    public function it_checks_a_member_in_and_attributes_the_visit_to_the_acting_user(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();

        $this->actingAs($owner)
            ->post(route('members.toggle-checkin', $member->id))
            ->assertRedirect()
            ->assertSessionHas('message', 'Mitglied wurde eingecheckt.');

        $this->assertDatabaseHas('check_ins', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_method' => 'manual',
            'checked_in_by' => $owner->id,
            'check_out_time' => null,
        ]);
    }

    #[Test]
    public function it_checks_an_already_checked_in_member_out(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();

        $checkin = CheckIn::create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subHour(),
            'check_in_method' => 'manual',
            'checked_in_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('members.toggle-checkin', $member->id))
            ->assertRedirect()
            ->assertSessionHas('message', 'Mitglied wurde ausgecheckt.');

        $this->assertNotNull($checkin->fresh()->check_out_time);
        $this->assertSame(1, CheckIn::where('member_id', $member->id)->count());
    }

    #[Test]
    public function it_opens_a_new_visit_when_the_previous_one_has_expired(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();

        // Older than the six-hour window: the member forgot to check out
        // yesterday, so today's click must start a fresh visit rather than
        // retroactively closing the stale one.
        $stale = CheckIn::create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subHours(9),
            'check_in_method' => 'manual',
        ]);

        $this->actingAs($owner)
            ->post(route('members.toggle-checkin', $member->id))
            ->assertSessionHas('message', 'Mitglied wurde eingecheckt.');

        $this->assertNull($stale->fresh()->check_out_time);
        $this->assertSame(2, CheckIn::where('member_id', $member->id)->count());
    }

    #[Test]
    public function it_checks_in_a_blocked_member_without_evaluating_access_rules(): void
    {
        // Staff explicitly decided to let this person in — the endpoint does not
        // second-guess them. See StaffCheckInService.
        [$owner, , $member] = $this->ownerGymMember(['status' => 'blocked']);

        $this->actingAs($owner)
            ->post(route('members.toggle-checkin', $member->id))
            ->assertSessionHas('message', 'Mitglied wurde eingecheckt.');

        $this->assertDatabaseHas('check_ins', [
            'member_id' => $member->id,
            'check_out_time' => null,
        ]);
    }

    #[Test]
    public function it_keeps_manual_check_ins_out_of_the_access_log(): void
    {
        // The scanner access log is the studio's live view of device and station
        // scans. A counter action by a staff member is not a hardware access
        // decision, so neither direction of the toggle may appear there.
        [$owner, , $member] = $this->ownerGymMember();

        $this->actingAs($owner)->post(route('members.toggle-checkin', $member->id));
        $this->actingAs($owner)->post(route('members.toggle-checkin', $member->id));

        $this->assertSame(0, ScannerAccessLog::where('member_id', $member->id)->count());
    }

    #[Test]
    public function it_still_attributes_the_visit_to_the_acting_user(): void
    {
        // Traceability moved off the access log entirely, so it has to hold on
        // the check_ins row itself.
        [$owner, $gym, $member] = $this->ownerGymMember();

        $this->actingAs($owner)->post(route('members.toggle-checkin', $member->id));

        $this->assertDatabaseHas('check_ins', [
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_method' => 'manual',
            'checked_in_by' => $owner->id,
        ]);
    }

    #[Test]
    public function it_flashes_the_result_in_the_shape_the_toast_bridge_reads(): void
    {
        // AppLayout turns page.props.flash.message into a toast, and
        // HandleInertiaRequests fills that from the session key 'message'.
        // Flashing 'success' instead would leave the operator without feedback.
        [$owner, , $member] = $this->ownerGymMember();

        $this->actingAs($owner)
            ->post(route('members.toggle-checkin', $member->id))
            ->assertSessionHas('message');

        $this->actingAs($owner)
            ->get(route('members.show', $member->id))
            ->assertInertia(fn ($page) => $page->where('flash.message', 'Mitglied wurde eingecheckt.'));
    }

    #[Test]
    public function it_denies_checking_in_a_member_of_another_gym(): void
    {
        [$owner] = $this->ownerGymMember();

        $otherGym = Gym::factory()->create();
        $foreignMember = Member::factory()->create(['gym_id' => $otherGym->id]);

        $this->actingAs($owner)
            ->post(route('members.toggle-checkin', $foreignMember->id))
            ->assertForbidden();

        $this->assertDatabaseCount('check_ins', 0);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        [, , $member] = $this->ownerGymMember();

        $this->post(route('members.toggle-checkin', $member->id))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('check_ins', 0);
    }

    #[Test]
    public function it_exposes_the_open_visit_on_the_member_detail_page(): void
    {
        [$owner, $gym, $member] = $this->ownerGymMember();

        $this->actingAs($owner)
            ->get(route('members.show', $member->id))
            ->assertInertia(fn ($page) => $page->where('openCheckin', null));

        CheckIn::create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subMinutes(10),
            'check_in_method' => 'manual',
        ]);

        $this->actingAs($owner)
            ->get(route('members.show', $member->id))
            ->assertInertia(fn ($page) => $page->where('openCheckin.id', $member->checkIns()->first()->id));
    }
}
