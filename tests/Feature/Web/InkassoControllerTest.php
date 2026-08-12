<?php

namespace Tests\Feature\Web;

use App\Models\CollectionCase;
use App\Models\CollectionRun;
use App\Models\DunningNotice;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InkassoControllerTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    private int $staffRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $this->staffRoleId = Role::factory()->create(['name' => 'Staff', 'slug' => 'staff'])->id;

        Http::fake([
            '*/Authenticate/login' => Http::response(['token' => 'jwt-token'], 200),
            '*/FileData/AddItem/*' => Http::response(['data' => ['guid' => 'guid-1']], 200),
            '*/PaymentData/AddItem/*' => Http::response(['data' => ['guid' => 'pay-1']], 200),
            '*/FileCancellation/AddItem/*' => Http::response(['data' => ['guid' => 'cancel-1']], 200),
        ]);
    }

    /**
     * @return array{0: User, 1: Gym}
     */
    private function ownerWithGym(bool $inkassoActive = true, int|string|null $roleId = null): array
    {
        $owner = User::factory()->create(['role_id' => $roleId ?? $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        if ($inkassoActive) {
            $gym->update([
                'inkasso_settings' => array_merge($gym->inkasso_settings, [
                    'active' => true,
                    'tenant_id' => '40218-BER',
                    'client_number' => '40218',
                    'username' => 'fitzone-berlin@api',
                    'password' => Crypt::encryptString('geheimespasswort'),
                ]),
            ]);
        }

        return [$owner->fresh(), $gym->fresh()];
    }

    private function readyMember(Gym $gym, float $amount = 99.98): Member
    {
        $member = Member::factory()->create([
            'gym_id' => $gym->id,
            'status' => 'overdue',
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
            'amount' => $amount,
            'currency' => 'EUR',
            'description' => 'Mitgliedsbeitrag',
            'status' => 'pending',
            'due_date' => Carbon::today()->subDays(60),
        ]);

        DunningNotice::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'level' => DunningNotice::LEVEL_SECOND_NOTICE,
            'fee' => 10,
            'triggered_at' => Carbon::now()->subDays(14),
        ]);

        return $member->fresh();
    }

    public function test_the_runs_overview_is_rendered(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $this->readyMember($gym);

        $this->actingAs($owner)
            ->get(route('finances.inkasso.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finances/Inkasso/Index')
                ->has('readyMembers', 1)
                ->has('statistics')
            );
    }

    public function test_staff_may_not_access_the_runs(): void
    {
        [, $gym] = $this->ownerWithGym();

        // A staff member of the same gym, not its owner.
        $staff = User::factory()->create(['role_id' => $this->staffRoleId]);
        $staff->update(['current_gym_id' => $gym->id]);
        $gym->users()->attach($staff->id, ['role' => 'staff']);

        $this->actingAs($staff->fresh())
            ->get(route('finances.inkasso.index'))
            ->assertForbidden();
    }

    public function test_a_run_can_be_created_for_selected_members(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $member = $this->readyMember($gym);

        $this->actingAs($owner)
            ->post(route('finances.inkasso.store'), ['member_ids' => [$member->id]])
            ->assertRedirect();

        $this->assertDatabaseCount('collection_runs', 1);
        $this->assertSame('blocked', $member->fresh()->status);
        // The case was transmitted to the partner.
        $this->assertSame('guid-1', CollectionCase::first()->diagonal_guid);
    }

    public function test_creating_a_run_requires_members(): void
    {
        [$owner] = $this->ownerWithGym();

        $this->actingAs($owner)
            ->post(route('finances.inkasso.store'), ['member_ids' => []])
            ->assertSessionHasErrors('member_ids');
    }

    public function test_a_run_of_another_gym_cannot_be_viewed(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $member = $this->readyMember($gym);
        $this->actingAs($owner)->post(route('finances.inkasso.store'), ['member_ids' => [$member->id]]);
        $run = CollectionRun::first();

        [$stranger] = $this->ownerWithGym();

        $this->actingAs($stranger)
            ->get(route('finances.inkasso.show', $run))
            ->assertForbidden();
    }

    public function test_a_member_can_be_handed_over_individually(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $member = $this->readyMember($gym);

        $this->actingAs($owner)
            ->post(route('members.inkasso.handover', $member), ['dunning_fee' => 10])
            ->assertRedirect();

        $this->assertDatabaseCount('collection_cases', 1);
        $this->assertSame('blocked', $member->fresh()->status);
    }

    public function test_handover_fails_without_an_active_partner(): void
    {
        [$owner, $gym] = $this->ownerWithGym(inkassoActive: false);
        $member = $this->readyMember($gym);

        $this->actingAs($owner)
            ->post(route('members.inkasso.handover', $member))
            ->assertSessionHasErrors('inkasso');

        $this->assertDatabaseCount('collection_cases', 0);
    }

    public function test_a_payment_can_be_booked_on_a_case(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $member = $this->readyMember($gym);
        $this->actingAs($owner)->post(route('members.inkasso.handover', $member));
        $case = CollectionCase::first();

        $this->actingAs($owner)
            ->post(route('inkasso.cases.payments.store', $case), [
                'amount' => 60,
                'booked_at' => Carbon::today()->toDateString(),
                'allocation_mode' => 'auto',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('collection_payments', 1);
        $this->assertEquals(60.0, (float) $case->fresh()->paid_amount);
    }

    public function test_booking_rejects_a_mismatching_manual_allocation(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $member = $this->readyMember($gym);
        $this->actingAs($owner)->post(route('members.inkasso.handover', $member));
        $case = CollectionCase::first();
        $claimId = $case->claims()->first()->id;

        $this->actingAs($owner)
            ->post(route('inkasso.cases.payments.store', $case), [
                'amount' => 60,
                'booked_at' => Carbon::today()->toDateString(),
                'allocation_mode' => 'manual',
                'allocation' => [$claimId => 10],
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_a_case_of_another_gym_cannot_be_modified(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $member = $this->readyMember($gym);
        $this->actingAs($owner)->post(route('members.inkasso.handover', $member));
        $case = CollectionCase::first();

        [$stranger] = $this->ownerWithGym();

        $this->actingAs($stranger)
            ->post(route('inkasso.cases.close', $case), [])
            ->assertForbidden();
    }

    public function test_the_partner_reference_can_be_updated(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $member = $this->readyMember($gym);
        $this->actingAs($owner)->post(route('members.inkasso.handover', $member));
        $case = CollectionCase::first();

        $this->actingAs($owner)
            ->put(route('inkasso.cases.reference.update', $case), ['partner_reference' => 'DG-884213'])
            ->assertRedirect();

        $this->assertSame('DG-884213', $case->fresh()->partner_reference);
    }

    public function test_the_export_streams_a_file(): void
    {
        [$owner, $gym] = $this->ownerWithGym();
        $member = $this->readyMember($gym);
        $this->actingAs($owner)->post(route('finances.inkasso.store'), ['member_ids' => [$member->id]]);
        $run = CollectionRun::first();

        $this->actingAs($owner)
            ->get(route('finances.inkasso.export', $run))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
