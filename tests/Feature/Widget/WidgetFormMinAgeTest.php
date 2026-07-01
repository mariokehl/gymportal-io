<?php

namespace Tests\Feature\Widget;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetFormMinAgeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mirror the template's own age-gate calculation. The Blade view uses the
     * native date()/strtotime() (system time), which Carbon::setTestNow does
     * not influence, so we compute the expected value the same way.
     */
    private function expectedMaxDate(int $minAge): string
    {
        return date('Y-m-d', strtotime('-' . $minAge . ' years'));
    }

    private function renderForm(array $features): string
    {
        $gymData = [
            'id' => 1,
            'name' => 'Test Gym',
            'mandate_name' => 'Test Gym',
            'creditor_identifier' => null,
            'widget_settings' => ['features' => $features],
            'payment_methods' => [],
        ];

        return view('widget.form', [
            'plan' => null,
            'savedFormData' => null,
            'gymData' => $gymData,
        ])->render();
    }

    public function test_default_min_age_uses_18_for_max_date_and_hint(): void
    {
        $html = $this->renderForm(['require_birth_date' => true]);

        // No min_age set => default of 18.
        $this->assertStringContainsString('max="' . $this->expectedMaxDate(18) . '"', $html);
        $this->assertStringContainsString('Du musst mindestens 18 Jahre alt sein', $html);
    }

    public function test_custom_min_age_below_18_is_used_for_max_date(): void
    {
        $html = $this->renderForm(['require_birth_date' => true, 'min_age' => 12]);

        // The actual age gate uses the configured value.
        $this->assertStringContainsString('max="' . $this->expectedMaxDate(12) . '"', $html);
    }

    public function test_hint_shows_15_for_any_min_age_below_18(): void
    {
        $html = $this->renderForm(['require_birth_date' => true, 'min_age' => 12]);

        $this->assertStringContainsString('Du musst mindestens 15 Jahre alt sein', $html);
        $this->assertStringNotContainsString('mindestens 12 Jahre', $html);
    }

    public function test_hint_shows_configured_age_when_18_or_above(): void
    {
        $html = $this->renderForm(['require_birth_date' => true, 'min_age' => 21]);

        $this->assertStringContainsString('max="' . $this->expectedMaxDate(21) . '"', $html);
        $this->assertStringContainsString('Du musst mindestens 21 Jahre alt sein', $html);
    }

    public function test_hint_is_hidden_when_birth_date_not_required(): void
    {
        $html = $this->renderForm(['require_birth_date' => false, 'min_age' => 16]);

        $this->assertStringNotContainsString('Du musst mindestens', $html);
        // Field is present but not required.
        $this->assertStringContainsString('name="birth_date"', $html);
    }
}
