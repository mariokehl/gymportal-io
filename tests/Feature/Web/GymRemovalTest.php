<?php

namespace Tests\Feature\Web;

use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use App\Services\GymRemovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GymRemovalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $berlin;

    private Gym $hamburg;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;

        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->berlin = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $this->hamburg = Gym::factory()->create(['owner_id' => $this->owner->id]);

        $this->owner->update(['current_gym_id' => $this->berlin->id]);
        $this->owner = $this->owner->fresh();
    }

    #[Test]
    public function it_removes_the_gym_with_all_of_its_data(): void
    {
        $plan = MembershipPlan::factory()->create(['gym_id' => $this->berlin->id]);
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);
        Membership::factory()->create(['member_id' => $member->id, 'membership_plan_id' => $plan->id]);
        CheckIn::factory()->create(['member_id' => $member->id, 'gym_id' => $this->berlin->id]);

        $this->actingAs($this->owner)
            ->delete(route('gyms.remove', $this->berlin))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('gyms', ['id' => $this->berlin->id]);
        $this->assertDatabaseMissing('members', ['id' => $member->id]);
        $this->assertDatabaseMissing('memberships', ['member_id' => $member->id]);
        $this->assertDatabaseMissing('check_ins', ['member_id' => $member->id]);
        $this->assertDatabaseMissing('membership_plans', ['id' => $plan->id]);
    }

    #[Test]
    public function it_clears_the_sensitive_data_of_the_gym(): void
    {
        $this->berlin->update([
            'account_holder' => 'FitZone Berlin GmbH',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'creditor_identifier' => 'DE98ZZZ09999999999',
            'api_key' => 'test-api-key',
            'api_key_generated_at' => now(),
            'mollie_config' => ['api_key' => 'test_dummy', 'test_mode' => true],
            'scanner_secret_key' => 'test-scanner-secret',
            'checkin_station_token' => 'test-station-token',
        ]);

        $this->actingAs($this->owner)->delete(route('gyms.remove', $this->berlin));

        $gym = Gym::withTrashed()->findOrFail($this->berlin->id);

        foreach (GymRemovalService::SENSITIVE_ATTRIBUTES as $attribute) {
            $this->assertNull($gym->getRawOriginal($attribute), "{$attribute} was not cleared");
        }
    }

    #[Test]
    public function it_keeps_the_data_of_other_gyms(): void
    {
        $otherPlan = MembershipPlan::factory()->create(['gym_id' => $this->hamburg->id]);
        $otherMember = Member::factory()->create(['gym_id' => $this->hamburg->id]);

        $this->actingAs($this->owner)->delete(route('gyms.remove', $this->berlin));

        $this->assertDatabaseHas('members', ['id' => $otherMember->id]);
        $this->assertDatabaseHas('membership_plans', ['id' => $otherPlan->id]);
        $this->assertNotSoftDeleted('gyms', ['id' => $this->hamburg->id]);
    }

    #[Test]
    public function it_switches_the_owner_to_another_owned_gym(): void
    {
        $this->actingAs($this->owner)->delete(route('gyms.remove', $this->berlin));

        $this->assertSame($this->hamburg->id, $this->owner->fresh()->current_gym_id);
    }

    #[Test]
    public function it_clears_the_current_gym_when_the_last_gym_is_removed(): void
    {
        $this->hamburg->delete();

        $this->actingAs($this->owner)->delete(route('gyms.remove', $this->berlin));

        $this->assertNull($this->owner->fresh()->current_gym_id);
    }

    #[Test]
    public function it_forbids_removing_a_gym_owned_by_someone_else(): void
    {
        $stranger = User::factory()->create(['role_id' => $this->owner->role_id]);
        $member = Member::factory()->create(['gym_id' => $this->berlin->id]);

        $this->actingAs($stranger)
            ->delete(route('gyms.remove', $this->berlin))
            ->assertForbidden();

        $this->assertNotSoftDeleted('gyms', ['id' => $this->berlin->id]);
        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }
}
