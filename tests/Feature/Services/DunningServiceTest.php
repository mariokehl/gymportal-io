<?php

namespace Tests\Feature\Services;

use App\Models\CollectionCase;
use App\Models\DunningNotice;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Services\DunningService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DunningServiceTest extends TestCase
{
    use RefreshDatabase;

    private DunningService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DunningService::class);
    }

    private function gym(array $settings = []): Gym
    {
        $gym = Gym::factory()->create();

        if ($settings !== []) {
            $gym->update(['inkasso_settings' => array_merge($gym->inkasso_settings, $settings)]);
            $gym->refresh();
        }

        return $gym;
    }

    private function overduePayment(
        Member $member,
        float $amount = 49.99,
        int $daysOverdue = 30,
        ?int $executedDaysAgo = null,
    ): Payment {
        return Payment::create([
            'gym_id' => $member->gym_id,
            'member_id' => $member->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'description' => 'Mitgliedsbeitrag',
            'status' => 'pending',
            'due_date' => Carbon::today()->subDays($daysOverdue),
            'execution_date' => $executedDaysAgo === null ? null : Carbon::today()->subDays($executedDaysAgo),
        ]);
    }

    public function test_it_escalates_to_level_one_after_the_configured_waiting_time(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'active']);
        $this->overduePayment($member, daysOverdue: 10);

        $notice = $this->service->escalate($member->fresh(), $gym);

        $this->assertNotNull($notice);
        $this->assertSame(DunningNotice::LEVEL_REMINDER, $notice->level);
        $this->assertEquals(0.0, (float) $notice->fee);
        $this->assertSame('overdue', $member->fresh()->status);
    }

    public function test_an_execution_date_after_the_due_date_delays_the_first_level(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'active']);

        // Due 20 days ago, but only executed 3 days ago: the reminder counts
        // from the execution and its 7 day waiting time has not elapsed yet.
        $this->overduePayment($member, daysOverdue: 20, executedDaysAgo: 3);

        $this->assertNull($this->service->escalate($member->fresh(), $gym));
    }

    public function test_an_execution_date_after_the_due_date_triggers_once_its_waiting_time_elapsed(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'active']);

        $this->overduePayment($member, daysOverdue: 40, executedDaysAgo: 10);

        $notice = $this->service->escalate($member->fresh(), $gym);

        $this->assertNotNull($notice);
        $this->assertSame(DunningNotice::LEVEL_REMINDER, $notice->level);
    }

    public function test_an_execution_date_before_the_due_date_keeps_the_due_date(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'active']);

        // Executed before it was due, so the due date stays in charge and its
        // 7 day waiting time has not elapsed yet.
        $this->overduePayment($member, daysOverdue: 3, executedDaysAgo: 20);

        $this->assertNull($this->service->escalate($member->fresh(), $gym));
    }

    public function test_without_an_execution_date_the_due_date_is_used(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'active']);

        $this->overduePayment($member, daysOverdue: 10, executedDaysAgo: null);

        $notice = $this->service->escalate($member->fresh(), $gym);

        $this->assertNotNull($notice);
        $this->assertSame(DunningNotice::LEVEL_REMINDER, $notice->level);
    }

    public function test_the_oldest_overdue_payment_is_measured_by_the_same_reference(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id, 'status' => 'active']);

        // Earlier due date, but executed late, so it started running last.
        $lateExecution = $this->overduePayment($member, daysOverdue: 60, executedDaysAgo: 8);
        // Later due date and no execution, so this one became overdue first.
        $earliest = $this->overduePayment($member, daysOverdue: 30);

        $notice = $this->service->escalate($member->fresh(), $gym);

        $this->assertNotNull($notice);
        $this->assertSame($earliest->id, $notice->payment_id);
        $this->assertNotSame($lateExecution->id, $notice->payment_id);
    }

    public function test_it_does_not_escalate_before_the_waiting_time_elapsed(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        // Level 1 triggers after 7 days by default.
        $this->overduePayment($member, daysOverdue: 3);

        $this->assertNull($this->service->escalate($member->fresh(), $gym));
        $this->assertSame(0, $member->fresh()->current_dunning_level);
    }

    public function test_it_escalates_step_by_step_and_books_the_configured_fee(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $this->overduePayment($member, daysOverdue: 60);

        // Level 1 - no fee.
        $this->service->escalate($member->fresh(), $gym);

        // Level 2 triggers 14 days after the previous notice.
        DunningNotice::where('member_id', $member->id)->update(['triggered_at' => Carbon::now()->subDays(20)]);
        $second = $this->service->escalate($member->fresh(), $gym);

        $this->assertSame(DunningNotice::LEVEL_FIRST_NOTICE, $second->level);
        $this->assertEquals(5.0, (float) $second->fee);

        // The fee becomes an additional open claim.
        $feePayment = Payment::where('member_id', $member->id)
            ->where('payment_method', 'dunning_fee')
            ->first();

        $this->assertNotNull($feePayment);
        $this->assertEquals(5.0, (float) $feePayment->amount);
        $this->assertSame('pending', $feePayment->status);
    }

    public function test_it_never_escalates_beyond_level_three(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $this->overduePayment($member, daysOverdue: 90);

        DunningNotice::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'level' => DunningNotice::LEVEL_SECOND_NOTICE,
            'fee' => 10,
            'triggered_at' => Carbon::now()->subDays(60),
        ]);

        // Level 4 is the handover and must only happen explicitly.
        $this->assertNull($this->service->escalate($member->fresh(), $gym));
        $this->assertSame(DunningNotice::LEVEL_SECOND_NOTICE, $member->fresh()->current_dunning_level);
    }

    public function test_it_does_not_escalate_while_the_member_is_in_collection(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $this->overduePayment($member, daysOverdue: 60);

        CollectionCase::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'case_number' => 'CASE-2026-0001',
            'status' => CollectionCase::STATUS_IN_PROGRESS,
            'handed_over_at' => Carbon::now()->subDay(),
        ]);

        $this->assertNull($this->service->escalate($member->fresh(), $gym));
    }

    public function test_dry_run_does_not_persist_anything(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $this->overduePayment($member, daysOverdue: 30);

        $this->service->escalate($member->fresh(), $gym, dryRun: true);

        $this->assertSame(0, DunningNotice::count());
        $this->assertSame(0, Payment::where('payment_method', 'dunning_fee')->count());
    }

    public function test_ready_for_collection_flags_members_below_the_minimum_amount(): void
    {
        $gym = $this->gym(['min_amount' => 10.0]);
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $this->overduePayment($member, amount: 8.5, daysOverdue: 60);

        DunningNotice::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'level' => DunningNotice::LEVEL_SECOND_NOTICE,
            'fee' => 10,
            'triggered_at' => Carbon::now()->subDays(10),
        ]);

        $rows = $this->service->readyForCollection($gym);

        $this->assertCount(1, $rows);
        $this->assertSame('Unter Mindestbetrag', $rows[0]['block']);
    }

    public function test_ready_for_collection_flags_minors_without_legal_guardian(): void
    {
        $gym = $this->gym(['include_minors' => false]);
        $member = Member::factory()->create([
            'gym_id' => $gym->id,
            'birth_date' => Carbon::today()->subYears(16),
        ]);
        $this->overduePayment($member, amount: 119.98, daysOverdue: 60);

        DunningNotice::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'level' => DunningNotice::LEVEL_SECOND_NOTICE,
            'fee' => 10,
            'triggered_at' => Carbon::now()->subDays(10),
        ]);

        $rows = $this->service->readyForCollection($gym);

        $this->assertSame('Minderjährig', $rows[0]['block']);
    }

    public function test_ready_for_collection_returns_eligible_members_without_blocker(): void
    {
        $gym = $this->gym();
        $member = Member::factory()->create(['gym_id' => $gym->id, 'birth_date' => Carbon::today()->subYears(30)]);
        $this->overduePayment($member, amount: 99.98, daysOverdue: 60);

        DunningNotice::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'level' => DunningNotice::LEVEL_SECOND_NOTICE,
            'fee' => 10,
            'triggered_at' => Carbon::now()->subDays(10),
        ]);

        $rows = $this->service->readyForCollection($gym);

        $this->assertNull($rows[0]['block']);
        $this->assertEquals(99.98, $rows[0]['open_amount']);
    }

    public function test_ready_for_collection_is_scoped_to_the_gym(): void
    {
        $gym = $this->gym();
        $otherGym = $this->gym();
        $foreign = Member::factory()->create(['gym_id' => $otherGym->id]);
        $this->overduePayment($foreign, amount: 99.98, daysOverdue: 60);

        DunningNotice::create([
            'gym_id' => $otherGym->id,
            'member_id' => $foreign->id,
            'level' => DunningNotice::LEVEL_SECOND_NOTICE,
            'fee' => 10,
            'triggered_at' => Carbon::now()->subDays(10),
        ]);

        $this->assertSame([], $this->service->readyForCollection($gym));
    }
}
