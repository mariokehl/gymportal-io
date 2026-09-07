<?php

namespace Tests\Unit\Models;

use App\Models\Membership;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipNextPossibleCancellationDateTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $planAttributes
     */
    private function membership(string $startDate, ?string $endDate, array $planAttributes): Membership
    {
        $plan = new MembershipPlan(array_merge([
            'commitment_months' => 1,
            'cancellation_period' => 30,
            'cancellation_period_unit' => 'days',
            'auto_renew_type' => 'monthly',
        ], $planAttributes));

        $membership = new Membership([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
        ]);
        $membership->setRelation('membershipPlan', $plan);

        return $membership;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null, 3: array<string, mixed>, 4: string}>
     */
    public static function cancellationDateProvider(): array
    {
        return [
            // One-month term signed on 01.09 with a 30-day notice period. The
            // deadline is 01.09 itself, so 30.09 is already out of reach and the
            // cancellation lands on the end of the renewed term.
            'monthly rollover, notice period already expired on day one' => [
                '2026-09-01', '2026-09-01', '2026-09-30',
                ['auto_renew_type' => 'monthly'],
                '2026-10-31',
            ],

            // Same term rolling over indefinitely: the cron clears end_date, so
            // only the 30-day notice period from today applies.
            'indefinite rollover, notice period already expired on day one' => [
                '2026-09-01', '2026-09-01', '2026-09-30',
                ['auto_renew_type' => 'indefinite'],
                '2026-10-01',
            ],

            // Deadline not reached yet: the current term end stays reachable.
            'before the deadline keeps the current term end' => [
                '2026-06-01', '2026-06-15', '2026-07-31',
                ['cancellation_period' => 1, 'cancellation_period_unit' => 'months', 'auto_renew_type' => 'monthly'],
                '2026-07-31',
            ],

            // Already open-ended: notice period from today, nothing else.
            'open-ended membership uses the notice period' => [
                '2026-01-01', '2026-07-01', null,
                ['cancellation_period' => 1, 'cancellation_period_unit' => 'months'],
                '2026-08-01',
            ],

            // Inside the initial term the notice period would end too early, so
            // the minimum cancellation date wins.
            'inside the initial term the commitment wins' => [
                '2026-01-01', '2026-01-15', null,
                ['commitment_months' => 12, 'cancellation_period' => 1, 'cancellation_period_unit' => 'months'],
                '2026-12-01',
            ],
        ];
    }

    #[Test]
    #[DataProvider('cancellationDateProvider')]
    public function it_resolves_the_next_possible_cancellation_date(
        string $startDate,
        string $today,
        ?string $endDate,
        array $planAttributes,
        string $expected
    ): void {
        Carbon::setTestNow(Carbon::parse($today));

        $membership = $this->membership($startDate, $endDate, $planAttributes);

        $this->assertSame($expected, $membership->nextPossibleCancellationDate());
    }

    #[Test]
    public function it_never_returns_a_date_in_the_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01'));

        foreach (['monthly', 'indefinite'] as $autoRenewType) {
            $membership = $this->membership('2026-09-01', '2026-09-30', [
                'auto_renew_type' => $autoRenewType,
            ]);

            $this->assertGreaterThan(
                Carbon::today()->toDateString(),
                $membership->nextPossibleCancellationDate(),
                "auto_renew_type={$autoRenewType} must not cancel to a past date",
            );
        }
    }
}
