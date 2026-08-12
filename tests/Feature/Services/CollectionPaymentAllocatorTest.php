<?php

namespace Tests\Feature\Services;

use App\Models\CollectionCase;
use App\Models\CollectionClaim;
use App\Models\Gym;
use App\Models\Member;
use App\Services\CollectionPaymentAllocator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionPaymentAllocatorTest extends TestCase
{
    use RefreshDatabase;

    private CollectionPaymentAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allocator = app(CollectionPaymentAllocator::class);
    }

    /**
     * @param  array<int, array{amount: float, paid?: float, days?: int, written_off?: bool}>  $claims
     */
    private function caseWithClaims(array $claims): CollectionCase
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);

        $case = CollectionCase::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'case_number' => CollectionCase::generateCaseNumber($gym->id),
            'status' => CollectionCase::STATUS_IN_PROGRESS,
            'handed_over_at' => now(),
        ]);

        foreach ($claims as $index => $claim) {
            CollectionClaim::create([
                'gym_id' => $gym->id,
                'collection_case_id' => $case->id,
                'description' => 'Forderung '.($index + 1),
                'due_date' => Carbon::today()->subDays($claim['days'] ?? (30 - $index)),
                'amount' => $claim['amount'],
                'paid_amount' => $claim['paid'] ?? 0,
                'written_off' => $claim['written_off'] ?? false,
                'kind' => CollectionClaim::KIND_PRINCIPAL,
            ]);
        }

        return $case->fresh();
    }

    public function test_it_fills_the_oldest_claim_first(): void
    {
        $case = $this->caseWithClaims([
            ['amount' => 49.99, 'days' => 60],
            ['amount' => 49.99, 'days' => 30],
        ]);

        $allocation = $this->allocator->allocateAutomatically($case, 60.0);
        $values = array_values($allocation);

        $this->assertCount(2, $allocation);
        $this->assertEqualsWithDelta(49.99, $values[0], 0.001);
        $this->assertEqualsWithDelta(10.01, $values[1], 0.001);
    }

    public function test_it_stops_when_the_amount_is_used_up(): void
    {
        $case = $this->caseWithClaims([
            ['amount' => 49.99, 'days' => 60],
            ['amount' => 49.99, 'days' => 30],
        ]);

        $allocation = $this->allocator->allocateAutomatically($case, 20.0);

        $this->assertCount(1, $allocation);
        $this->assertEqualsWithDelta(20.0, array_values($allocation)[0], 0.001);
    }

    public function test_it_never_allocates_more_than_the_open_amount(): void
    {
        $case = $this->caseWithClaims([
            ['amount' => 49.99, 'paid' => 40.0, 'days' => 60],
        ]);

        $allocation = $this->allocator->allocateAutomatically($case, 100.0);

        // Only the remaining 9.99 may be allocated.
        $this->assertEqualsWithDelta(9.99, $this->allocator->sum($allocation), 0.001);
    }

    public function test_written_off_and_settled_claims_are_skipped(): void
    {
        $case = $this->caseWithClaims([
            ['amount' => 49.99, 'paid' => 49.99, 'days' => 60],
            ['amount' => 10.0, 'written_off' => true, 'days' => 50],
            ['amount' => 25.0, 'days' => 40],
        ]);

        $allocation = $this->allocator->allocateAutomatically($case, 25.0);

        $this->assertCount(1, $allocation);
        $this->assertEqualsWithDelta(25.0, array_values($allocation)[0], 0.001);
    }

    public function test_validation_accepts_a_matching_allocation(): void
    {
        $case = $this->caseWithClaims([['amount' => 49.99, 'days' => 60]]);
        $claimId = $case->claims()->first()->id;

        $this->assertTrue($this->allocator->validate($case, [$claimId => 49.99], 49.99));
    }

    public function test_validation_rejects_mismatching_sums_and_overpayment(): void
    {
        $case = $this->caseWithClaims([['amount' => 49.99, 'days' => 60]]);
        $claimId = $case->claims()->first()->id;

        $this->assertFalse($this->allocator->validate($case, [$claimId => 30.0], 49.99));
        $this->assertFalse($this->allocator->validate($case, [$claimId => 60.0], 60.0));
        $this->assertFalse($this->allocator->validate($case, [], 0.0));
    }

    public function test_manual_allocation_drops_foreign_and_zero_entries(): void
    {
        $case = $this->caseWithClaims([['amount' => 49.99, 'days' => 60]]);
        $claimId = $case->claims()->first()->id;

        $normalised = $this->allocator->normaliseManual($case, [
            $claimId => 25.0,
            999999 => 10.0,
            0 => 5.0,
        ]);

        // Claims that do not belong to the case are dropped.
        $this->assertSame([$claimId => 25.0], $normalised);
    }

    public function test_manual_allocation_drops_zero_amounts(): void
    {
        $case = $this->caseWithClaims([
            ['amount' => 49.99, 'days' => 60],
            ['amount' => 25.0, 'days' => 40],
        ]);
        $ids = $case->claims()->orderBy('due_date')->pluck('id')->all();

        $normalised = $this->allocator->normaliseManual($case, [
            $ids[0] => 25.0,
            $ids[1] => 0,
        ]);

        $this->assertSame([$ids[0] => 25.0], $normalised);
    }
}
