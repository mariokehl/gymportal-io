<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancesSortingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Gym $gym;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $this->owner->id]);
        $this->owner->update(['current_gym_id' => $this->gym->id]);
        $this->owner = $this->owner->fresh();
        $this->member = Member::factory()->create(['gym_id' => $this->gym->id]);
    }

    private function createPayment(string $dueDate, ?string $executionDate = null): Payment
    {
        return Payment::create([
            'gym_id' => $this->gym->id,
            'member_id' => $this->member->id,
            'amount' => 25.00,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'status' => 'pending',
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
            ->all();
    }

    #[Test]
    public function payments_are_sorted_by_execution_date_descending_by_default(): void
    {
        $earliest = $this->createPayment('2026-01-31', '2026-01-10');
        $latest = $this->createPayment('2026-01-01', '2026-03-10');
        $middle = $this->createPayment('2026-01-15', '2026-02-10');

        $this->assertSame(
            [$latest->id, $middle->id, $earliest->id],
            $this->paymentIdsFromIndex()
        );
    }

    #[Test]
    public function the_due_date_is_used_when_no_execution_date_is_set(): void
    {
        // Only due dates: the order must follow them.
        $earliest = $this->createPayment('2026-01-10');
        $latest = $this->createPayment('2026-03-10');
        $middle = $this->createPayment('2026-02-10');

        $this->assertSame(
            [$latest->id, $middle->id, $earliest->id],
            $this->paymentIdsFromIndex()
        );
    }

    #[Test]
    public function execution_and_due_dates_are_ordered_against_each_other(): void
    {
        // The execution date wins over this payment's own due date, so it sorts
        // last even though its due date is the latest of all three.
        $byExecution = $this->createPayment('2026-12-01', '2026-01-05');
        $byDueDate = $this->createPayment('2026-02-05');
        $latest = $this->createPayment('2026-01-01', '2026-03-05');

        $this->assertSame(
            [$latest->id, $byDueDate->id, $byExecution->id],
            $this->paymentIdsFromIndex()
        );
    }

    #[Test]
    public function the_sort_order_can_be_reversed(): void
    {
        $earliest = $this->createPayment('2026-01-31', '2026-01-10');
        $latest = $this->createPayment('2026-01-01', '2026-03-10');

        $this->assertSame(
            [$earliest->id, $latest->id],
            $this->paymentIdsFromIndex(['sort_by' => 'scheduled_date', 'sort_order' => 'asc'])
        );
    }

    #[Test]
    public function the_applied_sorting_is_reported_back_to_the_view(): void
    {
        $response = $this->actingAs($this->owner)->get(route('finances.index'));

        $filters = $response->viewData('page')['props']['filters'];

        $this->assertSame('scheduled_date', $filters['sort_by']);
        $this->assertSame('desc', $filters['sort_order']);
    }

    #[Test]
    public function an_unknown_sort_column_falls_back_to_the_default(): void
    {
        $this->createPayment('2026-01-10');

        $response = $this->actingAs($this->owner)
            ->get(route('finances.index', ['sort_by' => 'execution_date; drop table payments']));

        $response->assertOk();
        $this->assertSame('scheduled_date', $response->viewData('page')['props']['filters']['sort_by']);
    }

    #[Test]
    public function other_sort_columns_still_work(): void
    {
        $first = $this->createPayment('2026-03-10');
        $second = $this->createPayment('2026-01-10');

        $this->assertSame(
            [$second->id, $first->id],
            $this->paymentIdsFromIndex(['sort_by' => 'id', 'sort_order' => 'desc'])
        );
    }
}
