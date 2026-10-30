<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Role;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SavedFilterTest extends TestCase
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

    private static function definition(string $status = 'active'): array
    {
        return [
            'rootLogic' => 'AND',
            'groups' => [['logic' => 'AND', 'conditions' => [
                ['id' => 'c1', 'field' => 'status', 'op' => 'any', 'value' => '', 'value2' => '', 'values' => [$status]],
            ]]],
        ];
    }

    #[Test]
    public function it_saves_a_sanitized_filter_and_overwrites_one_with_the_same_name(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $definition = self::definition();
        $definition['groups'][0]['conditions'][] = ['field' => 'unknown', 'op' => 'eq', 'value' => 'x'];
        $definition['groups'][0]['conditions'][0]['values'][] = 'not-a-status';

        $this->actingAs($owner)
            ->post(route('saved-filters.store'), ['context' => 'members', 'name' => 'Aktive', 'definition' => $definition])
            ->assertRedirect()
            ->assertSessionHas('success');

        $saved = SavedFilter::sole();
        $this->assertSame($gym->id, $saved->gym_id);
        $this->assertSame($owner->id, $saved->user_id);
        $this->assertCount(1, $saved->definition['groups'][0]['conditions']);
        $this->assertSame(['active'], $saved->definition['groups'][0]['conditions'][0]['values']);

        $this->actingAs($owner)
            ->post(route('saved-filters.store'), ['context' => 'members', 'name' => 'AKTIVE', 'definition' => self::definition('paused')])
            ->assertRedirect();

        $this->assertSame(1, SavedFilter::count());
        $this->assertSame(['paused'], SavedFilter::sole()->definition['groups'][0]['conditions'][0]['values']);

        $this->actingAs($owner)
            ->get(route('members.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('savedFilters', 1)
                ->where('savedFilters.0.name', 'AKTIVE')
            );
    }

    #[Test]
    public function it_validates_the_request(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('saved-filters.store'), ['context' => 'payments', 'name' => '', 'definition' => 'x'])
            ->assertSessionHasErrors(['context', 'name', 'definition']);
    }

    #[Test]
    public function saved_filters_stay_within_their_gym(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        [$otherOwner, $otherGym] = $this->ownerWithGym();
        $foreign = SavedFilter::create(['gym_id' => $otherGym->id, 'context' => 'members', 'name' => 'Fremd', 'definition' => self::definition()]);
        $own = SavedFilter::create(['gym_id' => $gym->id, 'context' => 'members', 'name' => 'Eigen', 'definition' => self::definition()]);

        $this->actingAs($owner)
            ->get(route('members.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('savedFilters', 1)
                ->where('savedFilters.0.id', $own->id)
            );

        $this->actingAs($owner)
            ->put(route('saved-filters.update', $foreign), ['name' => 'Übernommen', 'definition' => self::definition()])
            ->assertForbidden();
        $this->actingAs($owner)->delete(route('saved-filters.destroy', $foreign))->assertForbidden();
        $this->assertSame('Fremd', $foreign->fresh()->name);

        $this->actingAs($otherOwner)->delete(route('saved-filters.destroy', $foreign))->assertRedirect();
        $this->assertNull($foreign->fresh());
    }

    #[Test]
    public function it_limits_the_number_of_saved_filters(): void
    {
        [$owner, $gym] = $this->ownerWithGym();

        foreach (range(1, 50) as $number) {
            SavedFilter::create(['gym_id' => $gym->id, 'context' => 'members', 'name' => "Filter {$number}", 'definition' => self::definition()]);
        }

        $this->actingAs($owner)
            ->post(route('saved-filters.store'), ['context' => 'members', 'name' => 'Einer zu viel', 'definition' => self::definition()])
            ->assertSessionHasErrors('name');

        // Overwriting an existing one is still possible.
        $this->actingAs($owner)
            ->post(route('saved-filters.store'), ['context' => 'members', 'name' => 'Filter 1', 'definition' => self::definition('paused')])
            ->assertSessionHasNoErrors();
    }
}
