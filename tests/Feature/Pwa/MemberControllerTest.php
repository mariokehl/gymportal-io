<?php

namespace Tests\Feature\Pwa;

use App\Mail\CancellationConfirmationMail;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fullMember(array $memberOverrides = [], array $gymOverrides = []): array
    {
        $gym = Gym::factory()->create($gymOverrides);
        $member = Member::factory()->create(array_merge(['gym_id' => $gym->id], $memberOverrides));
        $token = $member->createToken('full', ['member-pwa', 'full'])->plainTextToken;

        return [$gym, $member, $token];
    }

    private function anonymousMember(array $memberOverrides = []): array
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(array_merge(['gym_id' => $gym->id], $memberOverrides));
        $token = $member->createToken('anon', ['member-pwa', 'anonymous'])->plainTextToken;

        return [$gym, $member, $token];
    }

    // ------------------------------------------------------------------
    // GET profile
    // ------------------------------------------------------------------

    #[Test]
    public function profile_requires_authentication(): void
    {
        $this->getJson('/api/pwa/member/profile')->assertStatus(401);
    }

    #[Test]
    public function profile_returns_full_data_for_full_session(): void
    {
        [$gym, $member, $token] = $this->fullMember(['phone' => '+49 123 456']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/pwa/member/profile')
            ->assertOk()
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.phone', '+49 123 456')
            ->assertJsonPath('data.gym.id', $gym->id);
    }

    #[Test]
    public function profile_returns_masked_data_for_anonymous_session(): void
    {
        [, , $token] = $this->anonymousMember();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/pwa/member/profile')
            ->assertOk()
            ->assertJsonPath('data.is_verified', false);

        $data = $response->json('data');
        $this->assertArrayHasKey('phone_masked', $data);
        $this->assertArrayNotHasKey('phone', $data);
    }

    // ------------------------------------------------------------------
    // GET contract / memberships
    // ------------------------------------------------------------------

    #[Test]
    public function contract_requires_authentication(): void
    {
        $this->getJson('/api/pwa/member/contract')->assertStatus(401);
    }

    #[Test]
    public function contract_returns_active_membership(): void
    {
        [$gym, $member, $token] = $this->fullMember();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/pwa/member/contract')
            ->assertOk()
            ->assertJsonPath('data.id', $membership->id);
    }

    #[Test]
    public function contract_returns_null_when_no_active_membership(): void
    {
        [, , $token] = $this->fullMember();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/pwa/member/contract')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function memberships_returns_overview_structure(): void
    {
        [, , $token] = $this->fullMember();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/pwa/member/memberships')
            ->assertOk()
            ->assertJsonStructure(['success', 'data' => ['current', 'free', 'paid']]);
    }

    // ------------------------------------------------------------------
    // PUT profile (requires full session)
    // ------------------------------------------------------------------

    #[Test]
    public function update_profile_requires_full_session(): void
    {
        [, , $anonToken] = $this->anonymousMember();

        $this->withHeader('Authorization', 'Bearer '.$anonToken)
            ->putJson('/api/pwa/member/profile', ['phone' => '0123'])
            ->assertStatus(403)
            ->assertJson(['error_code' => 'VERIFICATION_REQUIRED']);
    }

    #[Test]
    public function update_profile_succeeds_with_full_session(): void
    {
        [, $member, $token] = $this->fullMember();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/pwa/member/profile', [
                'phone' => '+49 999 12345',
                'city' => 'Berlin',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $member->refresh();
        $this->assertSame('+49 999 12345', $member->phone);
        $this->assertSame('Berlin', $member->city);
    }

    // ------------------------------------------------------------------
    // DELETE contract (requires full session)
    // ------------------------------------------------------------------

    #[Test]
    public function cancel_contract_requires_full_session(): void
    {
        [, , $anonToken] = $this->anonymousMember();

        $this->withHeader('Authorization', 'Bearer '.$anonToken)
            ->deleteJson('/api/pwa/member/contract')
            ->assertStatus(403);
    }

    #[Test]
    public function cancel_contract_returns_404_without_active_membership(): void
    {
        [, , $token] = $this->fullMember();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/pwa/member/contract')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function cancel_contract_sets_cancellation_date_and_dispatches_event(): void
    {
        Mail::fake();

        [$gym, $member, $token] = $this->fullMember();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/pwa/member/contract')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($membership->fresh()->cancellation_date);
        Mail::assertSent(CancellationConfirmationMail::class);
    }

    #[Test]
    public function cancel_contract_is_allowed_on_the_day_before_the_deadline(): void
    {
        Mail::fake();

        // Term ends 31.07, 1-month cancellation period -> deadline 01.07.
        // The last valid day to cancel is 30.06.
        Carbon::setTestNow(Carbon::parse('2026-06-30'));

        [$gym, $member, $token] = $this->fullMember();
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-31',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/pwa/member/contract')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('2026-07-31', $membership->fresh()->cancellation_date->toDateString());

        Carbon::setTestNow();
    }

    #[Test]
    public function cancel_contract_is_rejected_once_the_deadline_is_reached(): void
    {
        Mail::fake();

        // Same term, but now it is the deadline day itself (01.07): too late.
        Carbon::setTestNow(Carbon::parse('2026-07-01'));

        [$gym, $member, $token] = $this->fullMember();
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-31',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/pwa/member/contract')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNull($membership->fresh()->cancellation_date);
        Mail::assertNothingSent();

        Carbon::setTestNow();
    }

    #[Test]
    public function cancel_contract_after_renewal_targets_the_new_term_end(): void
    {
        Mail::fake();

        // On 01.07 the daily cron has already renewed the contract from 31.07 to
        // 31.08. A cancellation later that day is still valid, but for the new
        // term: its deadline is now 01.08, so today (01.07) is before it.
        Carbon::setTestNow(Carbon::parse('2026-07-01'));

        [$gym, $member, $token] = $this->fullMember();
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $gym->id,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/pwa/member/contract')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Cancellation takes effect at the renewed term end, not the missed one.
        $this->assertSame('2026-08-31', $membership->fresh()->cancellation_date->toDateString());

        Carbon::setTestNow();
    }

    // ------------------------------------------------------------------
    // GET qr-code (requires full session)
    // ------------------------------------------------------------------

    #[Test]
    public function qr_code_requires_full_session(): void
    {
        [, , $anonToken] = $this->anonymousMember();

        $this->withHeader('Authorization', 'Bearer '.$anonToken)
            ->getJson('/api/pwa/member/qr-code')
            ->assertStatus(403);
    }

    #[Test]
    public function qr_code_returns_static_qr_payload(): void
    {
        [, $member, $token] = $this->fullMember();
        MemberAccessConfig::factory()->create([
            'member_id' => $member->id,
            'qr_code_enabled' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/pwa/member/qr-code')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['qr_code', 'member' => ['first_name', 'last_name', 'member_number']],
            ]);

        $payload = json_decode($response->json('data.qr_code'), true);
        $this->assertSame((string) $member->id, $payload['member_id']);
        $this->assertArrayHasKey('hash', $payload);
        $this->assertArrayHasKey('timestamp', $payload);
    }

    #[Test]
    public function qr_code_returns_rolling_qr_payload(): void
    {
        [, $member, $token] = $this->fullMember(gymOverrides: [
            'rolling_qr_enabled' => true,
            'rolling_qr_interval' => 3,
        ]);
        MemberAccessConfig::factory()->create([
            'member_id' => $member->id,
            'qr_code_enabled' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/pwa/member/qr-code')
            ->assertOk();

        $payload = json_decode($response->json('data.qr_code'), true);
        $this->assertSame('rolling', $payload['type']);
        $this->assertArrayHasKey('totp_hash', $payload);
    }

    #[Test]
    public function qr_code_returns_403_when_disabled_in_access_config(): void
    {
        [, $member, $token] = $this->fullMember();
        MemberAccessConfig::factory()->create([
            'member_id' => $member->id,
            'qr_code_enabled' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/pwa/member/qr-code')
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // GET gyms
    // ------------------------------------------------------------------

    #[Test]
    public function gyms_requires_authentication(): void
    {
        $this->getJson('/api/pwa/member/gyms')->assertStatus(401);
    }

    #[Test]
    public function gyms_returns_sibling_gyms_of_owner(): void
    {
        $owner = User::factory()->create();
        $primary = Gym::factory()->create(['owner_id' => $owner->id]);
        Gym::factory()->count(2)->create(['owner_id' => $owner->id]);
        // Different owner — must not appear
        Gym::factory()->create();

        $member = Member::factory()->create(['gym_id' => $primary->id]);
        $token = $member->createToken('full', ['member-pwa', 'full'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/pwa/member/gyms')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['gyms' => [['id', 'slug', 'name']], 'current_gym_id'],
            ]);

        $this->assertCount(3, $response->json('data.gyms'));
        $this->assertSame($primary->id, $response->json('data.current_gym_id'));
    }
}
