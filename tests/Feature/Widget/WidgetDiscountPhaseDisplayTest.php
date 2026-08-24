<?php

namespace Tests\Feature\Widget;

use App\Models\MembershipPlan;
use App\Services\MembershipPlanDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The widget has to show a discounted plan the same way it will be billed:
 * the entry price leads, every later phase is named with the month it starts.
 */
class WidgetDiscountPhaseDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A monthly plan with a two-step discount ladder in front of its regular price.
     */
    private function discountedPlan(): MembershipPlan
    {
        $plan = MembershipPlan::factory()->create([
            'price' => 49.95,
            'original_price' => 54.95,
            'billing_cycle' => 'monthly',
            'commitment_months' => 6,
            'setup_fee' => 45.0,
            'discounts_enabled' => true,
        ]);

        (new MembershipPlanDiscountService)->sync($plan, [
            ['duration_months' => 3, 'price' => 19.95, 'original_price' => 54.95],
            ['duration_months' => 3, 'price' => 34.95, 'original_price' => 54.95],
        ]);

        return $plan->fresh(['discountPhases']);
    }

    #[Test]
    public function the_ladder_runs_from_the_phases_into_the_regular_price(): void
    {
        $segments = (new MembershipPlanDiscountService)->segmentsFor($this->discountedPlan());

        $this->assertCount(3, $segments);

        $this->assertSame([1, 3, 19.95, true], [
            $segments[0]['from'], $segments[0]['to'], $segments[0]['price'], $segments[0]['promo'],
        ]);
        $this->assertSame([4, 6, 34.95, true], [
            $segments[1]['from'], $segments[1]['to'], $segments[1]['price'], $segments[1]['promo'],
        ]);

        // The regular price closes the ladder and never ends.
        $this->assertSame(7, $segments[2]['from']);
        $this->assertNull($segments[2]['to']);
        $this->assertSame(49.95, $segments[2]['price']);
        $this->assertFalse($segments[2]['promo']);
    }

    #[Test]
    public function the_entry_price_is_the_first_phase_and_carries_its_discount(): void
    {
        $entry = (new MembershipPlanDiscountService)->entryPriceFor($this->discountedPlan());

        $this->assertSame(19.95, $entry['price']);
        $this->assertTrue($entry['has_discount']);
        $this->assertSame(54.95, $entry['original_price']);

        // 1 − 19.95 / 54.95 = 63.7 %.
        $this->assertSame(64, $entry['discount_percent']);
    }

    #[Test]
    public function the_contract_total_charges_every_phase_for_the_months_it_covers(): void
    {
        $total = (new MembershipPlanDiscountService)->contractTotalFor($this->discountedPlan());

        // 3 × 19.95 + 3 × 34.95 = 164.70 over the six-month term.
        $this->assertSame(164.70, $total);
    }

    #[Test]
    public function a_phase_without_its_own_uvp_falls_back_to_the_plan_price(): void
    {
        $plan = MembershipPlan::factory()->create([
            'price' => 49.95,
            'original_price' => 54.95,
            'billing_cycle' => 'monthly',
            'commitment_months' => 6,
            'discounts_enabled' => true,
        ]);

        // The phase editor shows the plan UVP as a placeholder, so an empty
        // field means "same as the plan" rather than "no reference price".
        (new MembershipPlanDiscountService)->sync($plan, [
            ['duration_months' => 3, 'price' => 19.95, 'original_price' => null],
            ['duration_months' => 3, 'price' => 34.95, 'original_price' => null],
        ]);

        $entry = (new MembershipPlanDiscountService)->entryPriceFor($plan->fresh(['discountPhases']));

        $this->assertTrue($entry['has_discount']);
        $this->assertSame(54.95, $entry['original_price']);
        $this->assertSame(64, $entry['discount_percent']);
    }

    #[Test]
    public function a_plan_without_a_ladder_collapses_to_its_regular_price(): void
    {
        $plan = MembershipPlan::factory()->create([
            'price' => 39.95,
            'original_price' => null,
            'billing_cycle' => 'monthly',
            'commitment_months' => 12,
            'discounts_enabled' => false,
        ]);

        $service = new MembershipPlanDiscountService;

        $this->assertCount(1, $service->segmentsFor($plan));
        $this->assertFalse($service->entryPriceFor($plan)['has_discount']);
        $this->assertSame(479.40, $service->contractTotalFor($plan));
    }

    #[Test]
    public function phases_are_ignored_on_a_billing_cycle_they_cannot_line_up_with(): void
    {
        $plan = MembershipPlan::factory()->create([
            'price' => 120.0,
            'billing_cycle' => 'yearly',
            'commitment_months' => 12,
            'discounts_enabled' => true,
        ]);

        // A yearly plan cannot carry a month-based ladder, so nothing is stored.
        (new MembershipPlanDiscountService)->sync($plan, [
            ['duration_months' => 3, 'price' => 60.0, 'original_price' => 120.0],
        ]);

        $segments = (new MembershipPlanDiscountService)->segmentsFor($plan->fresh(['discountPhases']));

        $this->assertCount(1, $segments);
        $this->assertSame(120.0, $segments[0]['price']);
    }

    #[Test]
    public function the_plan_card_leads_with_the_entry_price_and_names_the_later_phases(): void
    {
        $plans = MembershipPlan::where('id', $this->discountedPlan()->id)
            ->with(['addons', 'discountPhases'])
            ->get();

        $html = view('widget.plans', [
            'plans' => $plans,
            'gymData' => ['widget_settings' => []],
        ])->render();

        // The promo price leads, measured against the struck-through original.
        $this->assertStringContainsString('19,95', $html);
        $this->assertStringContainsString('54,95', $html);
        $this->assertStringContainsString('64%', $html);

        // Both follow-up phases are named with the month they start in.
        $this->assertStringContainsString('ab dem 4. Monat', $html);
        $this->assertStringContainsString('34,95', $html);
        $this->assertStringContainsString('ab dem 7. Monat', $html);
        $this->assertStringContainsString('49,95', $html);
    }

    #[Test]
    public function the_checkout_lists_one_contribution_row_per_phase_and_totals_them(): void
    {
        $plan = $this->discountedPlan();
        $service = new MembershipPlanDiscountService;

        $html = view('widget.checkout', [
            'formData' => ['country' => 'DE'],
            'gymData' => [
                'widget_settings' => [],
                'contracts_start_first_of_month' => false,
                'legal_urls' => [],
            ],
            'planData' => [
                'name' => $plan->name,
                'price' => $plan->price,
                'setup_fee' => $plan->setup_fee,
                'billing_cycle' => 'monthly',
                'commitment_months' => 6,
                'auto_renew_type' => 'monthly',
                'cancellation_period' => 30,
                'cancellation_period_unit' => 'days',
                'membership_price' => ['total_price' => 344.70],
                'discount_segments' => $service->segmentsFor($plan),
                'entry_price' => $service->entryPriceFor($plan),
                'discounted_contract_total' => $service->contractTotalFor($plan),
            ],
            'addons' => [],
        ])->render();

        // One row per phase, each naming the months it covers.
        $this->assertStringContainsString('Mitgliedsbeitrag Monat 1–3', $html);
        $this->assertStringContainsString('Mitgliedsbeitrag Monat 4–6', $html);
        $this->assertStringContainsString('Mitgliedsbeitrag ab dem 7. Monat', $html);

        // 164.70 of contributions + 45.00 activation fee = 209.70.
        $this->assertStringContainsString('209,70', $html);
    }

    #[Test]
    public function every_card_keeps_the_same_rows_so_the_grid_can_align_them(): void
    {
        $discounted = $this->discountedPlan();

        // A second plan on the same gym, without add-ons and without a ladder.
        $plain = MembershipPlan::factory()->create([
            'gym_id' => $discounted->gym_id,
            'price' => 39.95,
            'billing_cycle' => 'monthly',
            'commitment_months' => 12,
            'discounts_enabled' => false,
        ]);

        $plans = MembershipPlan::whereIn('id', [$discounted->id, $plain->id])
            ->with(['addons', 'discountPhases'])
            ->get();

        $html = view('widget.plans', [
            'plans' => $plans,
            'gymData' => ['widget_settings' => []],
        ])->render();

        // The cards are subgrid rows, so a fixed display value must never be
        // written onto them — it would silently drop them out of the grid.
        $this->assertStringNotContainsString('style="display: flex', $html);

        // Both rows that may be empty are still rendered on every card, so all
        // cards span the same number of grid rows.
        $this->assertSame(2, substr_count($html, 'class="plan-addons"'));
        $this->assertSame(2, substr_count($html, 'class="price-discount"'));
    }

    #[Test]
    public function the_renewal_note_states_the_term_and_the_configured_notice_period(): void
    {
        $note = function (string $renewType, int $period, string $unit, bool $startsFirstOfMonth = true): string {
            $html = view('widget.checkout', [
                'formData' => ['country' => 'DE'],
                'gymData' => [
                    'widget_settings' => [],
                    'contracts_start_first_of_month' => $startsFirstOfMonth,
                    'legal_urls' => [],
                ],
                'planData' => [
                    'name' => 'Premium',
                    'price' => 49.99,
                    'setup_fee' => 10.0,
                    'billing_cycle' => 'monthly',
                    'commitment_months' => 12,
                    'auto_renew_type' => $renewType,
                    'cancellation_period' => $period,
                    'cancellation_period_unit' => $unit,
                    'membership_price' => ['total_price' => 609.88],
                ],
                'addons' => [],
            ])->render();

            preg_match('/<p class="renewal-note">(.*?)<\/p>/s', $html, $matches);

            return trim(preg_replace('/\s+/', ' ', strip_tags($matches[1] ?? '')));
        };

        $this->assertSame(
            'Nach 12 Monaten läuft der Vertrag monatlich weiter. Kündigungsfrist: 1 Monat zum Monatsende.',
            $note('monthly', 1, 'months')
        );

        // A plan that does not renew monthly keeps its own wording, and the
        // notice period follows the plan instead of a fixed month.
        $this->assertSame(
            'Nach 12 Monaten geht der Vertrag in eine unbefristete Mitgliedschaft über. Kündigungsfrist: 30 Tage zum Monatsende.',
            $note('indefinite', 30, 'days')
        );

        // Without contracts pinned to the first of a month there is no common
        // month boundary to cancel to, so the suffix is left off.
        $this->assertSame(
            'Nach 12 Monaten läuft der Vertrag monatlich weiter. Kündigungsfrist: 1 Monat.',
            $note('monthly', 1, 'months', false)
        );
    }

    #[Test]
    public function the_checkout_offers_a_way_back_to_each_summarised_step(): void
    {
        $html = view('widget.checkout', [
            'formData' => ['country' => 'DE'],
            'gymData' => [
                'widget_settings' => [],
                'contracts_start_first_of_month' => false,
                'legal_urls' => [],
            ],
            'planData' => [
                'name' => 'Premium',
                'price' => 49.99,
                'setup_fee' => 10.0,
                'billing_cycle' => 'monthly',
                'commitment_months' => 12,
                'auto_renew_type' => 'monthly',
                'cancellation_period' => 30,
                'cancellation_period_unit' => 'days',
                'membership_price' => ['total_price' => 609.88],
            ],
            'addons' => [],
        ])->render();

        $this->assertStringContainsString('data-edit-step="plans"', $html);
        $this->assertStringContainsString('data-edit-step="form"', $html);
    }
}
