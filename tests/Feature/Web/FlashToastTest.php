<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FlashToastTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $owner = User::factory()->create(['role_id' => $roleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);
        $this->owner = $owner->fresh();
    }

    #[Test]
    public function a_flashed_message_reaches_exactly_the_next_response_as_a_toast(): void
    {
        $this->actingAs($this->owner)
            ->post(route('saved-filters.store'), [
                'context' => 'members',
                'name' => 'Aggregatoren',
                'definition' => ['rootLogic' => 'AND', 'groups' => []],
            ])
            ->assertRedirect();

        $this->get(route('members.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->hasFlash('toasts', [['type' => 'success', 'message' => 'Filter „Aggregatoren“ gespeichert']])
                ->missing('flash')
            );

        $this->get(route('members.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->missingFlash('toasts'));
    }

    #[Test]
    public function every_supported_session_key_becomes_a_typed_toast(): void
    {
        $this->actingAs($this->owner)
            ->withSession([
                'success' => 'Gespeichert',
                'message' => 'Erledigt',
                'info' => 'Hinweis',
                'warning' => 'Achtung',
                'error' => 'Fehlgeschlagen',
                'flash' => ['type' => 'error', 'message' => 'Tarif konnte nicht gelöscht werden'],
            ])
            ->get(route('members.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('toasts', [
                ['type' => 'success', 'message' => 'Gespeichert'],
                ['type' => 'success', 'message' => 'Erledigt'],
                ['type' => 'info', 'message' => 'Hinweis'],
                ['type' => 'warning', 'message' => 'Achtung'],
                ['type' => 'error', 'message' => 'Fehlgeschlagen'],
                ['type' => 'error', 'message' => 'Tarif konnte nicht gelöscht werden'],
            ]));
    }

    #[Test]
    public function a_message_survives_a_second_redirect(): void
    {
        // "/" only redirects; the toast must still reach the next page.
        $this->actingAs($this->owner)
            ->withSession(['success' => 'Weitergeleitet'])
            ->get('/')
            ->assertRedirect();

        $this->get(route('members.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->hasFlash('toasts', [['type' => 'success', 'message' => 'Weitergeleitet']]));
    }
}
