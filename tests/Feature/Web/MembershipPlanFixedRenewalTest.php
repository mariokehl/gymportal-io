<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipPlanFixedRenewalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;

        $this->owner = User::factory()->create(['role_id' => $ownerRoleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $this->owner->update(['current_gym_id' => $this->gym->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Altvertrag',
            'description' => 'Test plan',
            'price' => '39.90',
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'commitment_months' => 24,
            'cancellation_period' => 3,
            'cancellation_period_unit' => 'months',
            'auto_renew_type' => 'fixed',
            'renewal_months' => 12,
            'start_date_mode' => 'next_possible',
        ], $overrides);
    }

    #[Test]
    public function it_stores_a_fixed_renewal_term(): void
    {
        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload())
            ->assertSessionHasNoErrors();

        $plan = MembershipPlan::where('gym_id', $this->gym->id)->sole();

        $this->assertSame('fixed', $plan->auto_renew_type);
        $this->assertSame(12, $plan->renewal_months);
    }

    #[Test]
    public function it_requires_a_renewal_term_of_at_least_two_months(): void
    {
        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload(['renewal_months' => null]))
            ->assertSessionHasErrors('renewal_months');

        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload(['renewal_months' => 1]))
            ->assertSessionHasErrors('renewal_months');
    }

    #[Test]
    public function it_drops_the_renewal_term_for_other_renewal_types(): void
    {
        $this->actingAs($this->owner)
            ->post(route('contracts.store'), $this->planPayload(['auto_renew_type' => 'monthly']))
            ->assertSessionHasNoErrors();

        $this->assertNull(MembershipPlan::where('gym_id', $this->gym->id)->sole()->renewal_months);
    }

    #[Test]
    public function fixed_renewal_plans_are_not_sellable(): void
    {
        $sellable = MembershipPlan::factory()->create(['gym_id' => $this->gym->id, 'auto_renew_type' => 'monthly']);
        MembershipPlan::factory()->create(['gym_id' => $this->gym->id, 'auto_renew_type' => 'fixed', 'renewal_months' => 12]);

        $this->assertSame(
            [$sellable->id],
            MembershipPlan::where('gym_id', $this->gym->id)->sellable()->pluck('id')->all()
        );
    }
}
