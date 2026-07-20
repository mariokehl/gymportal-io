<?php

namespace Tests\Unit\Services;

use App\Models\MembershipPlan;
use App\Services\DiscountProjectionService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscountProjectionServiceTest extends TestCase
{
    private DiscountProjectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DiscountProjectionService;
    }

    /**
     * Build an unsaved plan; the projection never touches the database.
     */
    private function plan(float $price, ?int $commitmentMonths = null): MembershipPlan
    {
        return new MembershipPlan([
            'price' => $price,
            'commitment_months' => $commitmentMonths,
        ]);
    }

    /**
     * @param  list<array{duration_months: int, price: float|string}>  $phases
     */
    private function phases(array $phases): Collection
    {
        return collect($phases);
    }

    #[Test]
    public function it_projects_the_mcfit_style_template(): void
    {
        // 3 months at 19,95 € then 9 months at 34,95 €, regular 49,95 €, 12-month term.
        $result = $this->service->project(
            $this->plan(49.95, 12),
            $this->phases([
                ['duration_months' => 3, 'price' => 19.95],
                ['duration_months' => 9, 'price' => 34.95],
            ])
        );

        $this->assertSame(12, $result['term_months']);
        $this->assertSame(59940, $result['regular_total_cents']);      // 49,95 * 12
        $this->assertSame(37440, $result['discounted_total_cents']);   // 19,95*3 + 34,95*9
        $this->assertSame(22500, $result['savings_cents']);            // 225,00 €
        $this->assertSame('225,00 €', $this->service->formatCents($result['savings_cents']));
    }

    #[Test]
    public function it_appends_a_regular_price_segment_for_the_remainder_of_the_term(): void
    {
        $result = $this->service->project(
            $this->plan(50.00, 12),
            $this->phases([['duration_months' => 3, 'price' => 1.00]])
        );

        $this->assertCount(2, $result['segments']);

        [$promo, $regular] = $result['segments'];

        $this->assertTrue($promo['is_discounted']);
        $this->assertSame(1, $promo['start_month']);
        $this->assertSame(3, $promo['end_month']);
        $this->assertSame(100, $promo['price_cents']);

        $this->assertFalse($regular['is_discounted']);
        $this->assertSame(4, $regular['start_month']);
        $this->assertSame(12, $regular['end_month']);
        $this->assertSame(5000, $regular['price_cents']);

        // 1,00 * 3 + 50,00 * 9 = 453,00 €
        $this->assertSame(45300, $result['discounted_total_cents']);
    }

    #[Test]
    public function it_returns_a_single_regular_segment_when_there_are_no_phases(): void
    {
        $result = $this->service->project($this->plan(29.90, 6), $this->phases([]));

        $this->assertCount(1, $result['segments']);
        $this->assertFalse($result['segments'][0]['is_discounted']);
        $this->assertSame(0, $result['savings_cents']);
        $this->assertSame(
            $result['regular_total_cents'],
            $result['discounted_total_cents']
        );
    }

    #[Test]
    public function it_falls_back_to_a_default_horizon_without_a_minimum_commitment(): void
    {
        $result = $this->service->project($this->plan(10.00, null), $this->phases([]));

        $this->assertSame(DiscountProjectionService::DEFAULT_TERM_MONTHS, $result['term_months']);
    }

    #[Test]
    public function it_extends_the_term_so_phases_are_never_cut_off(): void
    {
        // 18 months of phases against a 12-month commitment.
        $result = $this->service->project(
            $this->plan(40.00, 12),
            $this->phases([['duration_months' => 18, 'price' => 20.00]])
        );

        $this->assertSame(18, $result['term_months']);
        $this->assertCount(1, $result['segments']);
        $this->assertSame(18, $result['segments'][0]['duration_months']);
        $this->assertTrue($result['exceeds_term']);
    }

    #[Test]
    public function it_flags_phases_exceeding_the_minimum_commitment(): void
    {
        $within = $this->service->project(
            $this->plan(40.00, 12),
            $this->phases([['duration_months' => 12, 'price' => 20.00]])
        );

        $this->assertFalse($within['exceeds_term']);

        $beyond = $this->service->project(
            $this->plan(40.00, 12),
            $this->phases([['duration_months' => 13, 'price' => 20.00]])
        );

        $this->assertTrue($beyond['exceeds_term']);
    }

    #[Test]
    public function it_ignores_blank_and_zero_length_phase_rows(): void
    {
        $result = $this->service->project(
            $this->plan(30.00, 6),
            $this->phases([
                ['duration_months' => 0, 'price' => 5.00],
                ['duration_months' => 2, 'price' => 10.00],
                ['duration_months' => '', 'price' => ''],
            ])
        );

        $discountedSegments = collect($result['segments'])->where('is_discounted', true);

        $this->assertCount(1, $discountedSegments);
        $this->assertSame(2, $discountedSegments->first()['duration_months']);
    }

    #[Test]
    public function it_treats_a_free_phase_as_zero_rather_than_falling_back_to_the_regular_price(): void
    {
        $result = $this->service->project(
            $this->plan(45.00, 12),
            $this->phases([['duration_months' => 3, 'price' => 0]])
        );

        $this->assertSame(0, $result['segments'][0]['price_cents']);
        // Only the 9 remaining months are charged: 45,00 * 9 = 405,00 €
        $this->assertSame(40500, $result['discounted_total_cents']);
        $this->assertSame(13500, $result['savings_cents']);
    }

    #[Test]
    public function it_never_reports_negative_savings_when_a_phase_costs_more(): void
    {
        $result = $this->service->project(
            $this->plan(20.00, 12),
            $this->phases([['duration_months' => 3, 'price' => 30.00]])
        );

        $this->assertSame(0, $result['savings_cents']);
    }

    #[Test]
    public function it_parses_german_decimal_input_without_float_drift(): void
    {
        $this->assertSame(1995, $this->service->toCents('19,95'));
        $this->assertSame(1995, $this->service->toCents('19.95'));
        $this->assertSame(123450, $this->service->toCents('1.234,50'));
        $this->assertSame(123450, $this->service->toCents('1,234.50'));
        $this->assertSame(1000, $this->service->toCents('10'));
        $this->assertSame(0, $this->service->toCents(''));
        $this->assertSame(0, $this->service->toCents(null));
        $this->assertSame(1995, $this->service->toCents('19,95 €'));
    }

    #[Test]
    public function it_accumulates_long_terms_without_rounding_error(): void
    {
        // 0,10 € is not representable in binary floating point; 240 months of it
        // is where naive float accumulation visibly drifts.
        $result = $this->service->project(
            $this->plan(0.10, 240),
            $this->phases([])
        );

        $this->assertSame(2400, $result['regular_total_cents']);
        $this->assertSame('24,00 €', $this->service->formatCents($result['regular_total_cents']));
    }

    #[Test]
    public function it_formats_cents_in_german_notation(): void
    {
        $this->assertSame('0,00 €', $this->service->formatCents(0));
        $this->assertSame('19,95 €', $this->service->formatCents(1995));
        $this->assertSame('1.234,50 €', $this->service->formatCents(123450));
    }
}
