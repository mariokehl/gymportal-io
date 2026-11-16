<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancesDueFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-16 10:00:00');

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $this->owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $this->owner->fresh();
        $this->member = Member::factory()->create(['gym_id' => $this->gym->id]);
    }

    private function createPayment(string $dueDate, ?string $executionDate = null, string $status = 'pending', ?Gym $gym = null): Payment
    {
        $gym ??= $this->gym;

        return Payment::create([
            'gym_id' => $gym->id,
            'member_id' => $gym->is($this->gym) ? $this->member->id : Member::factory()->create(['gym_id' => $gym->id])->id,
            'amount' => 25.00,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'status' => $status,
            'due_date' => $dueDate,
            'execution_date' => $executionDate,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function paymentIdsFromIndex(array $query = []): array
    {
        $response = $this->actingAs($this->owner)->get(route('finances.index', $query));
        $response->assertOk();

        return collect($response->viewData('page')['props']['payments']['data'])
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
    }

    #[Test]
    public function only_pending_payments_whose_date_has_been_reached_are_listed(): void
    {
        $pastDue = $this->createPayment('2026-11-01');
        $dueToday = $this->createPayment('2026-11-16');
        $executedToday = $this->createPayment('2026-12-01', '2026-11-16');
        $executedEarlier = $this->createPayment('2026-11-20', '2026-11-10');

        $this->createPayment('2026-11-17');
        // A future execution date postpones a due date in the past.
        $this->createPayment('2026-11-01', '2026-11-20');
        $this->createPayment('2026-11-01', null, 'paid');
        $this->createPayment('2026-11-01', null, 'failed');

        $this->assertSame(
            [$pastDue->id, $dueToday->id, $executedToday->id, $executedEarlier->id],
            $this->paymentIdsFromIndex(['due' => 1])
        );
    }

    #[Test]
    public function payments_of_other_gyms_are_not_listed(): void
    {
        $own = $this->createPayment('2026-11-01');
        $this->createPayment('2026-11-01', null, 'pending', Gym::factory()->create(['owner_id' => $this->owner->id]));

        $this->assertSame([$own->id], $this->paymentIdsFromIndex(['due' => 1]));
    }

    #[Test]
    public function all_payments_are_listed_without_the_filter(): void
    {
        $this->createPayment('2026-11-01');
        $this->createPayment('2026-12-01');
        $this->createPayment('2026-11-01', null, 'paid');

        $this->assertCount(3, $this->paymentIdsFromIndex());
    }

    #[Test]
    public function the_filter_state_is_reported_back_to_the_view(): void
    {
        $response = $this->actingAs($this->owner)->get(route('finances.index', ['due' => 1]));

        $this->assertTrue($response->viewData('page')['props']['filters']['due']);
    }

    #[Test]
    public function an_invalid_filter_value_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->get(route('finances.index', ['due' => 'yes please']))
            ->assertSessionHasErrors('due');
    }
}
