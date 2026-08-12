<?php

namespace Tests\Feature\Services;

use App\Models\CollectionCase;
use App\Models\CollectionPayment;
use App\Models\CollectionRun;
use App\Models\DunningNotice;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Services\CollectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CollectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CollectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CollectionService::class);
    }

    private function activeGym(array $settings = []): Gym
    {
        $gym = Gym::factory()->create();
        $gym->update([
            'inkasso_settings' => array_merge($gym->inkasso_settings, array_merge([
                'active' => true,
                'tenant_id' => '40218-BER',
                'client_number' => '40218',
                'username' => 'fitzone-berlin@api',
                'password' => Crypt::encryptString('geheimespasswort'),
            ], $settings)),
        ]);

        return $gym->fresh();
    }

    private function readyMember(Gym $gym, float $amount = 99.98, int $claims = 2): Member
    {
        $member = Member::factory()->create([
            'gym_id' => $gym->id,
            'status' => 'overdue',
            'birth_date' => Carbon::today()->subYears(30),
        ]);

        for ($i = 0; $i < $claims; $i++) {
            Payment::create([
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'amount' => round($amount / $claims, 2),
                'currency' => 'EUR',
                'description' => 'Mitgliedsbeitrag '.($i + 1),
                'status' => 'pending',
                'due_date' => Carbon::today()->subDays(60 - $i),
            ]);
        }

        DunningNotice::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'level' => DunningNotice::LEVEL_SECOND_NOTICE,
            'fee' => 10,
            'triggered_at' => Carbon::now()->subDays(14),
        ]);

        return $member->fresh();
    }

    public function test_handover_creates_case_claims_and_blocks_the_member(): void
    {
        $gym = $this->activeGym();
        $member = $this->readyMember($gym);

        $case = $this->service->handOverMember($member, $gym);

        $this->assertSame(CollectionCase::STATUS_IN_PROGRESS, $case->status);
        // Two overdue payments plus the handover flat fee.
        $this->assertSame(3, $case->claims()->count());
        $this->assertEquals(99.98, (float) $case->principal_amount);
        $this->assertEquals(58.5, (float) $case->flat_amount);
        $this->assertEquals(158.48, (float) $case->total_amount);

        $this->assertSame('blocked', $member->fresh()->status);
        $this->assertSame(DunningNotice::LEVEL_COLLECTION, $member->fresh()->current_dunning_level);

        // The original payments must no longer be collected by the studio.
        $this->assertSame(0, $member->payments()->overdue()->count());
    }

    public function test_handover_is_rejected_without_an_active_partner(): void
    {
        $gym = Gym::factory()->create();
        $member = $this->readyMember($gym);

        $this->expectException(RuntimeException::class);
        $this->service->handOverMember($member, $gym);
    }

    public function test_a_member_cannot_be_handed_over_twice(): void
    {
        $gym = $this->activeGym();
        $member = $this->readyMember($gym);
        $this->service->handOverMember($member, $gym);

        $this->expectException(RuntimeException::class);
        $this->service->handOverMember($member->fresh(), $gym);
    }

    public function test_create_run_excludes_members_below_the_minimum_amount(): void
    {
        $gym = $this->activeGym(['min_amount' => 10.0]);
        $eligible = $this->readyMember($gym, amount: 99.98);
        $tooSmall = $this->readyMember($gym, amount: 8.5, claims: 1);

        $run = $this->service->createRun($gym, [$eligible->id, $tooSmall->id]);

        $this->assertSame(1, $run->member_count);
        $this->assertSame('blocked', $eligible->fresh()->status);
        $this->assertNotSame('blocked', $tooSmall->fresh()->status);
    }

    public function test_run_totals_are_summed_from_the_cases(): void
    {
        $gym = $this->activeGym();
        $a = $this->readyMember($gym, amount: 99.98);
        $b = $this->readyMember($gym, amount: 149.97, claims: 3);

        $run = $this->service->createRun($gym, [$a->id, $b->id]);

        $this->assertSame(2, $run->member_count);
        $this->assertEquals(249.95, (float) $run->principal_amount);
        $this->assertEquals(117.0, (float) $run->flat_amount);
        $this->assertEquals(366.95, (float) $run->total_amount);
        $this->assertSame(CollectionRun::STATUS_HANDED_OVER, $run->status);
    }

    public function test_run_numbers_increment_per_gym(): void
    {
        $gym = $this->activeGym();
        $first = $this->service->createRun($gym, [$this->readyMember($gym)->id]);
        $second = $this->service->createRun($gym, [$this->readyMember($gym)->id]);

        $year = now()->year;
        $this->assertSame("IL-{$year}-001", $first->run_number);
        $this->assertSame("IL-{$year}-002", $second->run_number);
    }

    public function test_booking_a_payment_distributes_it_oldest_first(): void
    {
        $gym = $this->activeGym();
        $case = $this->service->handOverMember($this->readyMember($gym), $gym);

        $payment = $this->service->bookPayment($case, 60.0, Carbon::today());

        $this->assertEquals(60.0, (float) $payment->amount);

        $claims = $case->fresh()->claims()->orderBy('due_date')->get();
        // The oldest claim (49.99) is filled completely, the rest spills over.
        $this->assertEquals(49.99, (float) $claims[0]->paid_amount);
        $this->assertEquals(10.01, (float) $claims[1]->paid_amount);

        $this->assertSame(CollectionCase::STATUS_PARTIAL_PAYMENT, $case->fresh()->status);
        $this->assertEquals(60.0, (float) $case->fresh()->paid_amount);
    }

    public function test_manual_allocation_must_match_the_payment_amount(): void
    {
        $gym = $this->activeGym();
        $case = $this->service->handOverMember($this->readyMember($gym), $gym);
        $claim = $case->claims()->first();

        $this->expectException(RuntimeException::class);
        $this->service->bookPayment(
            $case,
            60.0,
            Carbon::today(),
            CollectionPayment::MODE_MANUAL,
            [$claim->id => 10.0],
        );
    }

    public function test_closing_a_case_writes_off_the_residual_claims(): void
    {
        $gym = $this->activeGym(['residual_handling' => Gym::RESIDUAL_ALWAYS_WRITE_OFF]);
        $member = $this->readyMember($gym);
        $case = $this->service->handOverMember($member, $gym);

        $this->service->closeCase($case);

        $case->refresh();
        $this->assertSame(CollectionCase::STATUS_COMPLETED, $case->status);
        $this->assertSame(0, $case->claims()->where('written_off', false)->count());
        // A completed case resets the dunning process.
        $this->assertSame(0, $member->fresh()->current_dunning_level);
    }

    public function test_closing_keeps_residual_claims_on_partner_decision(): void
    {
        $gym = $this->activeGym(['residual_handling' => Gym::RESIDUAL_PARTNER_DECISION]);
        $case = $this->service->handOverMember($this->readyMember($gym), $gym);

        $this->service->closeCase($case);

        $this->assertGreaterThan(0, $case->fresh()->claims()->where('written_off', false)->count());
    }

    public function test_cancelling_a_case_releases_the_member(): void
    {
        $gym = $this->activeGym();
        $member = $this->readyMember($gym);
        $case = $this->service->handOverMember($member, $gym);

        $this->service->cancelCase($case);

        $this->assertSame(CollectionCase::STATUS_CANCELLED, $case->fresh()->status);
        $this->assertSame('overdue', $member->fresh()->status);
        // Levels 1-3 survive a cancellation.
        $this->assertSame(DunningNotice::LEVEL_SECOND_NOTICE, $member->fresh()->current_dunning_level);
    }

    public function test_undoing_a_run_cancels_the_cases_and_keeps_dunning_levels(): void
    {
        $gym = $this->activeGym();
        $member = $this->readyMember($gym);
        $run = $this->service->createRun($gym, [$member->id]);

        $this->service->undoRun($run);

        $this->assertSame(CollectionRun::STATUS_CANCELLED, $run->fresh()->status);
        $this->assertSame(CollectionCase::STATUS_CANCELLED, $run->cases()->first()->status);
        $this->assertSame('overdue', $member->fresh()->status);
        $this->assertSame(DunningNotice::LEVEL_SECOND_NOTICE, $member->fresh()->current_dunning_level);
    }

    public function test_rejecting_a_case_stores_the_reason(): void
    {
        $gym = $this->activeGym();
        $case = $this->service->handOverMember($this->readyMember($gym), $gym);

        $this->service->rejectCase($case, 'Postzustellung nicht möglich');

        $case->refresh();
        $this->assertSame(CollectionCase::STATUS_REJECTED, $case->status);
        $this->assertSame('Postzustellung nicht möglich', $case->rejection_reason);
    }

    public function test_transmitting_a_case_stores_the_returned_guid(): void
    {
        $gym = $this->activeGym(['client_number' => '40218']);
        $member = Member::factory()->create([
            'gym_id' => $gym->id,
            'first_name' => 'Susi',
            'last_name' => 'Summs',
            'address' => 'Musterstraße 12a',
            'postal_code' => '10115',
            'city' => 'Berlin',
            'birth_date' => Carbon::today()->subYears(30),
        ]);
        Payment::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'amount' => 49.99,
            'currency' => 'EUR',
            'description' => 'Mitgliedsbeitrag',
            'status' => 'pending',
            'due_date' => Carbon::today()->subDays(60),
        ]);

        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200),
            '*/FileData/AddItem/*' => Http::response(['data' => ['guid' => 'guid-1']], 200),
        ]);

        $case = $this->service->handOverMember($member->fresh(), $gym);
        $guid = $this->service->transmitCase($case, $gym);

        $this->assertSame('guid-1', $guid);
        $this->assertSame('guid-1', $case->fresh()->diagonal_guid);
        $this->assertSame('submitted', $case->fresh()->diagonal_state);
    }

    public function test_a_failed_transmission_keeps_the_local_case(): void
    {
        $gym = $this->activeGym(['client_number' => '40218']);
        $member = $this->readyMember($gym);

        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200),
            '*/FileData/AddItem/*' => Http::response(['message' => 'Adresse unvollständig'], 422),
        ]);

        $case = $this->service->handOverMember($member, $gym);
        $guid = $this->service->transmitCase($case, $gym);

        $this->assertNull($guid);
        // The handover itself must survive so it can be retransmitted.
        $this->assertSame(CollectionCase::STATUS_IN_PROGRESS, $case->fresh()->status);
        $this->assertSame('error', $case->fresh()->diagonal_state);
        $this->assertSame('blocked', $member->fresh()->status);
    }

    public function test_statistics_only_count_the_own_gym(): void
    {
        $gym = $this->activeGym();
        $otherGym = $this->activeGym();
        $this->service->handOverMember($this->readyMember($gym), $gym);
        $this->service->handOverMember($this->readyMember($otherGym), $otherGym);

        $stats = $this->service->statistics($gym);

        $this->assertSame(1, $stats['in_collection_count']);
        $this->assertEquals(158.48, $stats['in_collection_amount']);
    }
}
