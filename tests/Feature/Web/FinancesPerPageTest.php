<?php

namespace Tests\Feature\Web;

use App\Http\Requests\Finances\FinanceIndexRequest;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancesPerPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $this->owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $this->owner->fresh();

        $member = Member::factory()->create(['gym_id' => $this->gym->id]);

        for ($i = 0; $i < 30; $i++) {
            Payment::create([
                'gym_id' => $this->gym->id,
                'member_id' => $member->id,
                'amount' => 25.00,
                'currency' => 'EUR',
                'description' => 'Beitrag',
                'status' => 'pending',
                'due_date' => '2026-01-01',
            ]);
        }
    }

    #[Test]
    public function the_default_page_size_is_used_without_a_choice(): void
    {
        $response = $this->actingAs($this->owner)->get(route('finances.index'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(FinanceIndexRequest::DEFAULT_PER_PAGE, $props['perPage']);
        $this->assertSame(FinanceIndexRequest::PER_PAGE_OPTIONS, $props['perPageOptions']);
        $this->assertCount(25, $props['payments']['data']);
        $this->assertSame(30, $props['payments']['total']);
        $response->assertCookieMissing(FinanceIndexRequest::PER_PAGE_COOKIE);
    }

    #[Test]
    public function a_chosen_page_size_is_applied_and_remembered(): void
    {
        $response = $this->actingAs($this->owner)->get(route('finances.index', ['per_page' => 50]));

        $response->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame(50, $props['perPage']);
        $this->assertCount(30, $props['payments']['data']);
        $response->assertCookie(FinanceIndexRequest::PER_PAGE_COOKIE, '50');
    }

    #[Test]
    public function the_remembered_page_size_is_used_on_the_next_visit(): void
    {
        $response = $this->actingAs($this->owner)
            ->withCookie(FinanceIndexRequest::PER_PAGE_COOKIE, '100')
            ->get(route('finances.index'));

        $response->assertOk();
        $this->assertSame(100, $response->viewData('page')['props']['perPage']);
    }

    #[Test]
    public function a_tampered_cookie_falls_back_to_the_default(): void
    {
        $response = $this->actingAs($this->owner)
            ->withCookie(FinanceIndexRequest::PER_PAGE_COOKIE, '100000')
            ->get(route('finances.index'));

        $response->assertOk();
        $this->assertSame(FinanceIndexRequest::DEFAULT_PER_PAGE, $response->viewData('page')['props']['perPage']);
    }

    #[Test]
    public function an_unsupported_page_size_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->get(route('finances.index', ['per_page' => 100000]))
            ->assertSessionHasErrors('per_page');
    }
}
