<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpiredOrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Gym $expiredGym;

    private Gym $activeGym;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->user = User::factory()->create(['role_id' => $roleId]);
        $this->expiredGym = Gym::factory()->withoutActiveSubscription()->create(['owner_id' => $this->user->id]);
        $this->activeGym = Gym::factory()->create(['owner_id' => $this->user->id]);
        $this->user->update(['current_gym_id' => $this->expiredGym->id]);
    }

    #[Test]
    public function a_user_can_leave_an_expired_organization_for_an_active_one(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('user.switch-organization'), ['gym_id' => $this->activeGym->id]);

        $response->assertRedirect();
        $this->assertNotSame(route('billing.index'), $response->headers->get('Location'));
        $this->assertSame($this->activeGym->id, $this->user->fresh()->current_gym_id);
    }

    #[Test]
    public function the_expired_organization_still_gates_its_premium_pages(): void
    {
        $this->actingAs($this->user)
            ->get(route('members.index'))
            ->assertRedirect(route('billing.index'));
    }

    #[Test]
    public function switching_into_a_foreign_organization_stays_forbidden(): void
    {
        $foreignGym = Gym::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs($this->user)
            ->post(route('user.switch-organization'), ['gym_id' => $foreignGym->id])
            ->assertForbidden();

        $this->assertSame($this->expiredGym->id, $this->user->fresh()->current_gym_id);
    }

    #[Test]
    public function the_create_organization_page_opens_while_the_organization_is_expired(): void
    {
        $this->actingAs($this->user)
            ->get(route('gyms.create'))
            ->assertOk();
    }

    #[Test]
    public function a_new_organization_can_be_created_while_the_organization_is_expired(): void
    {
        $this->actingAs($this->user)
            ->post(route('gyms.store'), [
                'name' => 'Studio Nord',
                'address' => 'Hauptstraße 1',
                'city' => 'Kassel',
                'postal_code' => '34117',
                'country' => 'DE',
                'phone' => '0561 000000',
                'email' => 'studio-nord@example.test',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $newGym = Gym::where('name', 'Studio Nord')->firstOrFail();

        $this->assertSame($this->user->id, $newGym->owner_id);
        $this->assertTrue($newGym->isInTrial());
        $this->assertSame($newGym->id, $this->user->fresh()->current_gym_id);

        // The new organization's trial unlocks the premium pages again.
        $this->actingAs($this->user->fresh())
            ->get(route('members.index'))
            ->assertOk();
    }
}
