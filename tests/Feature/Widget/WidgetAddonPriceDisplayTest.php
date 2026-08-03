<?php

namespace Tests\Feature\Widget;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WidgetAddonPriceDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Render the plan selection with a single optional add-on attached.
     */
    private function renderPlansWithAddon(Addon $addon): string
    {
        $gym = Gym::factory()->create();

        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Premium',
            'price' => 49.99,
        ]);

        $addon->gym_id = $gym->id;
        $addon->save();

        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        $plans = MembershipPlan::where('id', $plan->id)
            ->with(['addons' => fn ($query) => $query->where('is_active', true)])
            ->get();

        return view('widget.plans', [
            'plans' => $plans,
            'gymData' => ['widget_settings' => []],
        ])->render();
    }

    #[Test]
    public function it_leads_with_the_weekly_price_and_keeps_the_monthly_amount_visible(): void
    {
        $html = $this->renderPlansWithAddon(
            Addon::factory()->weeklyPriceDisplay()->make(['price' => 8.62])
        );

        // 8.62 × 12 ÷ 52 = 1.99 leads as the comparison figure.
        $this->assertStringContainsString('1,99', $html);
        $this->assertStringContainsString('/ Woche (rechnerisch)', $html);

        // The monthly amount that is actually billed stays visible.
        $this->assertStringContainsString('8,62', $html);
        $this->assertStringContainsString('Abrechnung monatlich', $html);
    }

    #[Test]
    public function it_shows_only_the_monthly_price_by_default(): void
    {
        $html = $this->renderPlansWithAddon(
            Addon::factory()->recurring()->make(['price' => 8.62])
        );

        $this->assertStringContainsString('8,62', $html);
        $this->assertStringContainsString('monatlich', $html);

        // No weekly figure is derived for the monthly display.
        $this->assertStringNotContainsString('/ Woche (rechnerisch)', $html);
        $this->assertStringNotContainsString('1,99', $html);
    }

    #[Test]
    public function a_one_time_addon_never_shows_a_weekly_price(): void
    {
        // Even with the weekly display stored, one-off billing has no weekly
        // equivalent, so the widget falls back to the plain price.
        $html = $this->renderPlansWithAddon(Addon::factory()->make([
            'price' => 60.0,
            'billing_type' => Addon::BILLING_TYPE_ONE_TIME,
            'price_display' => Addon::PRICE_DISPLAY_WEEKLY,
        ]));

        $this->assertStringContainsString('60,00', $html);
        $this->assertStringContainsString('einmalig', $html);
        $this->assertStringNotContainsString('/ Woche (rechnerisch)', $html);
    }
}
