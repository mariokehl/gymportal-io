<?php

namespace Tests\Feature\Widget;

use App\Models\Addon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WidgetCheckoutAddonBillingTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Render the checkout summary with a single paid add-on.
     */
    private function renderCheckout(array $addon, int $commitmentMonths = 12): string
    {
        return view('widget.checkout', [
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
                'commitment_months' => $commitmentMonths,
                'auto_renew_type' => 'monthly',
                'cancellation_period' => 30,
                'cancellation_period_unit' => 'days',
                'membership_price' => ['total_price' => 609.88],
            ],
            'addons' => [$addon],
        ])->render();
    }

    #[Test]
    public function a_one_time_addon_is_labelled_and_counted_once(): void
    {
        $html = $this->renderCheckout([
            'name' => 'Trainereinweisung',
            'price' => 60.0,
            'mode' => 'optional',
            'billing_type' => Addon::BILLING_TYPE_ONE_TIME,
            'is_recurring' => false,
        ]);

        $this->assertStringContainsString('einmalig', $html);

        // 609.88 plan total + 60.00 charged once.
        $this->assertStringContainsString('669,88', $html);
    }

    #[Test]
    public function a_recurring_addon_is_labelled_monthly_and_counted_per_month(): void
    {
        $html = $this->renderCheckout([
            'name' => 'Getränke-Flatrate',
            'price' => 8.62,
            'mode' => 'optional',
            'billing_type' => Addon::BILLING_TYPE_RECURRING,
            'is_recurring' => true,
        ]);

        $this->assertStringContainsString('monatlich', $html);

        // 609.88 plan total + 8.62 * 12 months = 713.32.
        $this->assertStringContainsString('713,32', $html);
    }

    #[Test]
    public function an_included_addon_is_never_added_to_the_total(): void
    {
        $html = $this->renderCheckout([
            'name' => 'Getränke-Flatrate',
            'price' => 8.62,
            'mode' => 'included',
            'billing_type' => Addon::BILLING_TYPE_RECURRING,
            'is_recurring' => true,
        ]);

        // Free for the member: 0,00 € leads, the regular price stays as the
        // struck-through reference behind its billing rhythm.
        $this->assertStringContainsString('addon-gift-price', $html);
        $this->assertStringContainsString('0,00', $html);
        $this->assertStringContainsString('8,62', $html);

        // The plan total stays untouched.
        $this->assertStringContainsString('609,88', $html);
    }
}
