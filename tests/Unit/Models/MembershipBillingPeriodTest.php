<?php

namespace Tests\Unit\Models;

use App\Models\Membership;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipBillingPeriodTest extends TestCase
{
    private function membershipStarting(string $startDate): Membership
    {
        $membership = new Membership;
        $membership->start_date = Carbon::parse($startDate);

        return $membership;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function billingPeriodEndProvider(): array
    {
        return [
            // Contract started mid-month: periods run 21. → 20. of next month.
            'mid-month, before the anchor day' => ['2026-07-21', '2026-07-21', '2026-08-20'],
            'mid-month, on the anchor day' => ['2026-07-21', '2026-08-21', '2026-09-20'],
            'mid-month, day before the anchor' => ['2026-07-21', '2026-08-20', '2026-08-20'],

            // Contract started on the 1st ("Verträge immer zum 1. des Monats"),
            // so periods line up with calendar months.
            'first of month' => ['2026-07-01', '2026-07-15', '2026-07-31'],
            'first of month, on the anchor' => ['2026-07-01', '2026-08-01', '2026-08-31'],

            // Short months clamp to their last day.
            'anchor on the 31st into February' => ['2026-01-31', '2026-02-01', '2026-02-27'],
            'anchor on the 31st in a 30-day month' => ['2026-01-31', '2026-04-15', '2026-04-29'],

            // Year rollover.
            'december into january' => ['2026-03-10', '2026-12-20', '2027-01-09'],
        ];
    }

    #[Test]
    #[DataProvider('billingPeriodEndProvider')]
    public function it_resolves_the_end_of_the_current_billing_period(
        string $startDate,
        string $today,
        string $expected
    ): void {
        $membership = $this->membershipStarting($startDate);

        $this->assertSame(
            $expected,
            $membership->billingPeriodEnd(Carbon::parse($today))->toDateString()
        );
    }

    #[Test]
    public function the_period_end_is_the_day_before_the_next_charge(): void
    {
        $membership = $this->membershipStarting('2026-07-21');

        $end = $membership->billingPeriodEnd(Carbon::parse('2026-07-21'));
        $nextStart = $membership->billingPeriodStart(Carbon::parse('2026-08-21'));

        $this->assertSame('2026-08-20', $end->toDateString());
        $this->assertSame('2026-08-21', $nextStart->toDateString());
        $this->assertTrue($end->copy()->addDay()->eq($nextStart));
    }

    #[Test]
    public function it_returns_null_without_a_start_date(): void
    {
        $membership = new Membership;

        $this->assertNull($membership->billingPeriodStart());
        $this->assertNull($membership->billingPeriodEnd());
    }
}
