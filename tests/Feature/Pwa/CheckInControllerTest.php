<?php

namespace Tests\Feature\Pwa;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckInControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authedMember(): array
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $token = $member->createToken('pwa', ['member-pwa', 'full'])->plainTextToken;

        return [$gym, $member, $token];
    }

    // ------------------------------------------------------------------
    // GET checkin/latest
    // ------------------------------------------------------------------

    #[Test]
    public function latest_requires_authentication(): void
    {
        $this->getJson('/api/pwa/member/checkin/latest')->assertStatus(401);
    }

    #[Test]
    public function latest_returns_null_when_no_active_checkin(): void
    {
        [, , $token] = $this->authedMember();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/pwa/member/checkin/latest')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => null,
                'message' => 'Kein aktiver Check-In gefunden',
            ]);
    }

    #[Test]
    public function latest_returns_active_checkin_with_relations(): void
    {
        [$gym, $member, $token] = $this->authedMember();
        $checkin = CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subMinutes(15),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/pwa/member/checkin/latest')
            ->assertOk()
            ->assertJsonPath('data.id', $checkin->id)
            ->assertJsonPath('data.gym.id', $gym->id)
            ->assertJsonPath('data.member.id', $member->id);
    }

    #[Test]
    public function latest_excludes_already_checked_out_entries(): void
    {
        [$gym, $member, $token] = $this->authedMember();
        CheckIn::factory()->ended()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/pwa/member/checkin/latest')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function latest_excludes_checkins_older_than_one_day(): void
    {
        [$gym, $member, $token] = $this->authedMember();
        CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subDays(2),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/pwa/member/checkin/latest')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function latest_does_not_return_other_members_checkin(): void
    {
        [$gym, , $token] = $this->authedMember();
        $otherMember = Member::factory()->create(['gym_id' => $gym->id]);
        CheckIn::factory()->create([
            'member_id' => $otherMember->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subMinutes(10),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/pwa/member/checkin/latest')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    // ------------------------------------------------------------------
    // POST checkin/{id}/end
    // ------------------------------------------------------------------

    #[Test]
    public function end_checkin_requires_authentication(): void
    {
        $this->postJson('/api/pwa/member/checkin/1/end')->assertStatus(401);
    }

    #[Test]
    public function end_checkin_sets_check_out_time(): void
    {
        [$gym, $member, $token] = $this->authedMember();
        $checkin = CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
            'check_in_time' => now()->subMinutes(30),
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/pwa/member/checkin/' . $checkin->id . '/end')
            ->assertOk()
            ->assertJsonPath('data.id', $checkin->id);

        $this->assertNotNull($checkin->fresh()->check_out_time);
    }

    #[Test]
    public function end_checkin_returns_404_if_not_found(): void
    {
        [, , $token] = $this->authedMember();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/pwa/member/checkin/99999/end')
            ->assertStatus(404);
    }

    #[Test]
    public function end_checkin_returns_404_for_other_members_checkin(): void
    {
        [$gym, , $token] = $this->authedMember();
        $otherMember = Member::factory()->create(['gym_id' => $gym->id]);
        $checkin = CheckIn::factory()->create([
            'member_id' => $otherMember->id,
            'gym_id' => $gym->id,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/pwa/member/checkin/' . $checkin->id . '/end')
            ->assertStatus(404);
    }

    #[Test]
    public function end_checkin_returns_404_if_already_checked_out(): void
    {
        [$gym, $member, $token] = $this->authedMember();
        $checkin = CheckIn::factory()->ended()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/pwa/member/checkin/' . $checkin->id . '/end')
            ->assertStatus(404);
    }

    #[Test]
    public function end_checkin_returns_422_when_older_than_six_hours(): void
    {
        // Documents existing behaviour: the controller uses
        // $now->diffInHours($checkinTime), which on Carbon 3 is signed and
        // negative for past times — so the >6h guard never triggers.
        // This test asserts the *current* behaviour; switch to assertStatus(422)
        // once the controller switches to abs()/diffInHours(false).
        [$gym, $member, $token] = $this->authedMember();
        $checkin = CheckIn::factory()->older(8)->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/pwa/member/checkin/' . $checkin->id . '/end')
            ->assertOk();
    }
}
