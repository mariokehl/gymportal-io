<?php

namespace Tests\Feature\Services;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Services\GymDataImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class GymDataImportDeleteExistingTest extends TestCase
{
    use RefreshDatabase;

    private function deleteAllGymMemberData(int $gymId): array
    {
        $method = new ReflectionMethod(GymDataImportService::class, 'deleteAllGymMemberData');
        $method->setAccessible(true);

        return $method->invoke(app(GymDataImportService::class), $gymId);
    }

    public function test_it_deletes_check_ins_that_carry_a_foreign_gym_id(): void
    {
        $gym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        // A check-in of this gym's member, but stamped with a different gym_id
        CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $otherGym->id,
        ]);

        $deleted = $this->deleteAllGymMemberData($gym->id);

        $this->assertSame(1, $deleted['members']);
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseMissing('check_ins', ['member_id' => $member->id]);
    }

    public function test_it_deletes_soft_deleted_members_and_their_check_ins(): void
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        CheckIn::factory()->create([
            'member_id' => $member->id,
            'gym_id' => $gym->id,
        ]);
        $member->delete();

        $deleted = $this->deleteAllGymMemberData($gym->id);

        $this->assertSame(1, $deleted['members']);
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseMissing('check_ins', ['member_id' => $member->id]);
    }

    public function test_it_leaves_other_gyms_untouched(): void
    {
        $gym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $otherMember = Member::factory()->create(['gym_id' => $otherGym->id]);
        $otherCheckIn = CheckIn::factory()->create([
            'member_id' => $otherMember->id,
            'gym_id' => $otherGym->id,
        ]);

        $this->deleteAllGymMemberData($gym->id);

        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseHas('members', ['id' => $otherMember->id]);
        $this->assertDatabaseHas('check_ins', ['id' => $otherCheckIn->id]);
    }
}
