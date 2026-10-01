<?php

namespace Tests\Unit\Models;

use App\Models\Membership;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipRenewalMonthsTest extends TestCase
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
            'commitment_months' => 12,
            'cancellation_period' => 3,
            'cancellation_period_unit' => 'months',
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
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: int|null}>
     */
    public static function renewalMonthsProvider(): array
    {
        return [
            'indefinite converts to open-ended' => [
                '2021-01-01', ['auto_renew_type' => 'indefinite'], null,
            ],
            'monthly renews by one month' => [
                '2021-01-01', ['auto_renew_type' => 'monthly'], 1,
            ],
            'legacy contract keeps the fixed renewal term' => [
                '2021-01-01', ['auto_renew_type' => 'fixed', 'renewal_months' => 12], 12,
            ],
            'last day before the cutoff still counts as legacy' => [
                '2022-02-28', ['auto_renew_type' => 'fixed', 'renewal_months' => 24], 24,
            ],
            'contract from the cutoff on falls back to monthly' => [
                '2022-03-01', ['auto_renew_type' => 'fixed', 'renewal_months' => 12], 1,
            ],
            'missing renewal term falls back to monthly' => [
                '2021-01-01', ['auto_renew_type' => 'fixed', 'renewal_months' => null], 1,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $planAttributes
     */
    #[Test]
    #[DataProvider('renewalMonthsProvider')]
    public function it_resolves_the_renewal_term(string $startDate, array $planAttributes, ?int $expected): void
    {
        $this->assertSame($expected, $this->membership($startDate, '2026-12-31', $planAttributes)->renewalMonths());
    }

    #[Test]
    public function it_projects_the_fixed_renewal_once_the_deadline_has_passed(): void
    {
        // Legacy contract, term ends 31.12.2026, 3-month notice: deadline 01.10.2026.
        Carbon::setTestNow('2026-10-01');

        $membership = $this->membership('2021-01-01', '2026-12-31', [
            'auto_renew_type' => 'fixed',
            'renewal_months' => 12,
        ]);

        $this->assertSame('2027-12-31', $membership->projected_end_date);
        $this->assertSame('2027-12-31', $membership->next_possible_cancellation_date);
    }

    #[Test]
    public function it_keeps_the_current_term_end_before_the_deadline(): void
    {
        Carbon::setTestNow('2026-09-30');

        $membership = $this->membership('2021-01-01', '2026-12-31', [
            'auto_renew_type' => 'fixed',
            'renewal_months' => 12,
        ]);

        $this->assertSame('2026-12-31', $membership->projected_end_date);
    }
}
