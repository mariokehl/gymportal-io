<?php

namespace Tests\Feature\Web;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\MembershipDiscountService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The discount a member gets is frozen when the contract is signed, and every
 * charge afterwards resolves its amount from that frozen copy.
 */
class MembershipDiscountBillingTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private MembershipDiscountService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->service = app(MembershipDiscountService::class);
    }

    /**
     * A monthly plan at 49,95 € with a 3 + 9 month discount ladder.
     */
    private function discountedPlan(array $overrides = []): MembershipPlan
    {
        $plan = MembershipPlan::factory()->create(array_merge([
            'gym_id' => $this->gym->id,
            'price' => 49.95,
            'billing_cycle' => 'monthly',
            'discounts_enabled' => true,
            'commitment_months' => 12,
        ], $overrides));

        $plan->discountPhases()->createMany([
            ['sort_order' => 0, 'duration_months' => 3, 'price' => 19.95],
            ['sort_order' => 1, 'duration_months' => 9, 'price' => 34.95],
        ]);

        return $plan->refresh();
    }

    private function membership(MembershipPlan $plan, string $startDate = '2026-01-01'): Membership
    {
        $member = Member::factory()->create(['gym_id' => $this->gym->id]);

        return Membership::create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => $startDate,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_freezes_the_discount_ladder_onto_a_new_membership(): void
    {
        $membership = $this->membership($this->discountedPlan());

        $phases = $membership->discountPhases()->get();

        $this->assertCount(2, $phases);
        $this->assertSame(3, $phases[0]->duration_months);
        $this->assertSame('19.95', $phases[0]->price);
        $this->assertSame(9, $phases[1]->duration_months);
        $this->assertSame('34.95', $phases[1]->price);
    }

    #[Test]
    public function it_charges_each_month_the_price_of_its_phase(): void
    {
        $membership = $this->membership($this->discountedPlan());

        // Months 1-3 sit in the first phase, 4-12 in the second.
        $this->assertSame('19.95', $this->service->priceFor($membership, Carbon::parse('2026-01-01')));
        $this->assertSame('19.95', $this->service->priceFor($membership, Carbon::parse('2026-03-15')));
        $this->assertSame('34.95', $this->service->priceFor($membership, Carbon::parse('2026-04-01')));
        $this->assertSame('34.95', $this->service->priceFor($membership, Carbon::parse('2026-12-01')));
    }

    #[Test]
    public function it_falls_back_to_the_regular_price_once_the_phases_run_out(): void
    {
        $membership = $this->membership($this->discountedPlan());

        // Month 13 is past the 12 discounted months.
        $this->assertNull($this->service->priceFor($membership, Carbon::parse('2027-01-01')));
    }

    #[Test]
    public function it_keeps_the_signed_discount_when_the_plan_ladder_changes_later(): void
    {
        $plan = $this->discountedPlan();
        $membership = $this->membership($plan);

        // The operator rewrites the promotion after the contract was signed.
        $plan->discountPhases()->delete();
        $plan->discountPhases()->create([
            'sort_order' => 0,
            'duration_months' => 12,
            'price' => 9.99,
        ]);

        $this->assertSame('19.95', $this->service->priceFor($membership, Carbon::parse('2026-01-01')));
        $this->assertSame('34.95', $this->service->priceFor($membership, Carbon::parse('2026-06-01')));
    }

    #[Test]
    public function it_keeps_the_signed_discount_when_the_plan_discount_is_switched_off(): void
    {
        $plan = $this->discountedPlan();
        $membership = $this->membership($plan);

        $plan->update(['discounts_enabled' => false]);

        $this->assertSame('19.95', $this->service->priceFor($membership, Carbon::parse('2026-01-01')));
    }

    #[Test]
    public function a_contract_signed_after_the_change_gets_the_new_ladder(): void
    {
        $plan = $this->discountedPlan();
        $existing = $this->membership($plan);

        $plan->discountPhases()->delete();
        $plan->discountPhases()->create([
            'sort_order' => 0,
            'duration_months' => 6,
            'price' => 9.99,
        ]);

        $fresh = $this->membership($plan->refresh());

        $this->assertSame('19.95', $this->service->priceFor($existing, Carbon::parse('2026-01-01')));
        $this->assertSame('9.99', $this->service->priceFor($fresh, Carbon::parse('2026-01-01')));
    }

    #[Test]
    public function it_takes_no_snapshot_when_the_plan_has_no_discounts(): void
    {
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'price' => 49.95,
            'billing_cycle' => 'monthly',
            'discounts_enabled' => false,
        ]);

        $membership = $this->membership($plan);

        $this->assertSame(0, $membership->discountPhases()->count());
        $this->assertNull($this->service->priceFor($membership, Carbon::parse('2026-01-01')));
    }

    #[Test]
    public function it_takes_no_snapshot_when_the_billing_cycle_is_not_monthly(): void
    {
        $plan = $this->discountedPlan(['billing_cycle' => 'yearly']);

        $membership = $this->membership($plan);

        $this->assertSame(0, $membership->discountPhases()->count());
    }

    #[Test]
    public function a_membership_without_a_snapshot_is_charged_the_regular_price(): void
    {
        $plan = $this->discountedPlan();
        $membership = $this->membership($plan);
        $membership->discountPhases()->delete();

        $this->assertNull($this->service->priceFor($membership->refresh(), Carbon::parse('2026-01-01')));
    }

    #[Test]
    public function it_ignores_a_billing_date_before_the_contract_start(): void
    {
        $membership = $this->membership($this->discountedPlan(), '2026-06-01');

        $this->assertNull($this->service->priceFor($membership, Carbon::parse('2026-05-01')));
    }

    #[Test]
    public function it_counts_months_by_calendar_month_not_by_day(): void
    {
        // A contract starting on the 31st must not lose a month against a
        // billing date on the 30th.
        $membership = $this->membership($this->discountedPlan(), '2026-01-31');

        $this->assertSame('19.95', $this->service->priceFor($membership, Carbon::parse('2026-03-30')));
        $this->assertSame('34.95', $this->service->priceFor($membership, Carbon::parse('2026-04-30')));
    }
}
