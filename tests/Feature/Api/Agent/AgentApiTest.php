<?php

namespace Tests\Feature\Api\Agent;

use App\Models\AgentApiLog;
use App\Models\CheckIn;
use App\Models\Gym;
use App\Models\GymApiClient;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\Membership;
use App\Models\MembershipPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The Agent API lets an automation act for one gym. These tests pin down the
 * two promises it makes: the tenant comes from the token, and a token can only
 * do what its abilities say.
 */
class AgentApiTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Gym $otherGym;

    private MembershipPlan $plan;

    private Member $member;

    private Membership $membership;

    private Member $stranger;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->gym = Gym::factory()->create();
        $this->otherGym = Gym::factory()->create();

        $this->plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Flex 12',
            'price' => 29.90,
            'commitment_months' => 12,
            'cancellation_period' => 1,
            'cancellation_period_unit' => 'months',
        ]);

        $this->member = Member::factory()->create([
            'gym_id' => $this->gym->id,
            'status' => 'active',
            'email' => 'mia@example.org',
            'phone' => '0171 2345678',
            'member_number' => 'M-1001',
        ]);

        $this->membership = Membership::factory()->create([
            'member_id' => $this->member->id,
            'membership_plan_id' => $this->plan->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'status' => 'active',
        ]);

        MemberAccessConfig::create(['member_id' => $this->member->id, 'qr_code_enabled' => true]);

        $this->stranger = Member::factory()->create([
            'gym_id' => $this->otherGym->id,
            'status' => 'active',
            'email' => 'fremd@example.org',
        ]);

        $this->token = $this->issue(GymApiClient::ABILITIES);
    }

    private function issue(array $abilities, ?Gym $gym = null): string
    {
        return GymApiClient::issue($gym ?? $this->gym, 'Testagent', 'Tests', $abilities)[1];
    }

    private function agent(?string $token = null): static
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.($token ?? $this->token),
            'Accept' => 'application/json',
        ]);
    }

    private function url(string $path): string
    {
        return '/api/v1/agent/'.ltrim($path, '/');
    }

    // --- authentication -------------------------------------------------

    #[Test]
    public function it_rejects_requests_without_or_with_an_unknown_token(): void
    {
        $this->getJson($this->url('health'))->assertStatus(401);
        $this->agent('gpa_'.str_repeat('x', 48))->getJson($this->url('health'))->assertStatus(401);
        $this->agent('not-even-the-prefix')->getJson($this->url('health'))->assertStatus(401);
    }

    #[Test]
    public function it_rejects_revoked_and_expired_tokens(): void
    {
        [$revoked, $revokedToken] = GymApiClient::issue($this->gym, 'Alt', 'Tests', ['plans.read']);
        $revoked->revoke();

        [, $expiredToken] = GymApiClient::issue($this->gym, 'Abgelaufen', 'Tests', ['plans.read'], now()->subDay());

        $this->agent($revokedToken)->getJson($this->url('plans'))->assertStatus(401);
        $this->agent($expiredToken)->getJson($this->url('plans'))->assertStatus(401);
    }

    #[Test]
    public function the_token_is_stored_hashed_only(): void
    {
        $client = GymApiClient::where('gym_id', $this->gym->id)->first();

        $this->assertSame(hash('sha256', $this->token), $client->token_hash);
        $this->assertStringNotContainsString($this->token, json_encode($client->getAttributes()));
    }

    #[Test]
    public function a_token_without_the_ability_gets_403(): void
    {
        $plansOnly = $this->issue(['plans.read']);

        $this->agent($plansOnly)
            ->getJson($this->url('members'))
            ->assertStatus(403)
            ->assertJsonPath('error.required_ability', 'members.read');

        $this->agent($plansOnly)->getJson($this->url("members/{$this->member->id}/payments"))->assertStatus(403);
        $this->agent($plansOnly)->getJson($this->url('plans'))->assertOk();
    }

    #[Test]
    public function a_gym_without_access_or_removed_ends_the_token(): void
    {
        $this->agent()->getJson($this->url('health'))->assertOk();

        $this->gym->update(['subscription_status' => 'inactive', 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDay()]);
        $this->agent()->getJson($this->url('health'))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'gym_without_access');

        $this->gym->delete();
        $this->agent()->getJson($this->url('health'))->assertStatus(401);
    }

    #[Test]
    public function issuing_refuses_unknown_abilities_and_an_empty_purpose(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GymApiClient::issue($this->gym, 'X', 'Tests', ['members.delete']);
    }

    // --- tenancy --------------------------------------------------------

    #[Test]
    public function a_member_of_another_gym_does_not_exist_for_this_token(): void
    {
        $id = $this->stranger->id;

        $this->agent()->getJson($this->url("members/{$id}"))->assertStatus(404);
        $this->agent()->getJson($this->url("members/{$id}/contract"))->assertStatus(404);
        $this->agent()->getJson($this->url("members/{$id}/access"))->assertStatus(404);
        $this->agent()->getJson($this->url("members/{$id}/payments"))->assertStatus(404);

        $this->agent()->getJson($this->url('members/lookup?email=fremd@example.org'))
            ->assertOk()->assertJsonPath('data.match', 'none');

        $list = $this->agent()->getJson($this->url('members'))->assertOk()->json('data.members');
        $this->assertSame([$this->member->id], array_column($list, 'id'));
    }

    #[Test]
    public function plans_of_another_gym_are_not_listed(): void
    {
        MembershipPlan::factory()->create(['gym_id' => $this->otherGym->id, 'name' => 'Fremdtarif']);

        $names = array_column($this->agent()->getJson($this->url('plans'))->assertOk()->json('data.plans'), 'name');

        $this->assertSame(['Flex 12'], $names);
    }

    // --- reading --------------------------------------------------------

    #[Test]
    public function health_names_the_gym_and_the_abilities(): void
    {
        $this->agent()->getJson($this->url('health'))
            ->assertOk()
            ->assertJsonPath('data.gym.id', $this->gym->id)
            ->assertJsonPath('data.client.name', 'Testagent');
    }

    #[Test]
    public function lookup_finds_a_member_by_email_number_and_phone(): void
    {
        $this->agent()->getJson($this->url('members/lookup?email=MIA@example.org'))
            ->assertOk()->assertJsonPath('data.match', 'unique')
            ->assertJsonPath('data.members.0.id', $this->member->id);

        $this->agent()->getJson($this->url('members/lookup?member_number=M-1001'))
            ->assertOk()->assertJsonPath('data.match', 'unique');

        $this->agent()->getJson($this->url('members/lookup?phone='.urlencode('+49 171 2345678')))
            ->assertOk()->assertJsonPath('data.match', 'unique');

        $this->agent()->getJson($this->url('members/lookup?email=niemand@example.org'))
            ->assertOk()->assertJsonPath('data.match', 'none');

        $this->agent()->getJson($this->url('members/lookup'))->assertStatus(422);
    }

    #[Test]
    public function lookup_by_phone_finds_every_member_not_only_the_first_results(): void
    {
        // More members than any page size, each with its own number. Without an
        // ORDER BY a database returns some subset first, so every one is looked up.
        $members = Member::factory()->count(30)
            ->sequence(fn ($sequence) => ['phone' => sprintf('0170 %07d', $sequence->index + 1)])
            ->create(['gym_id' => $this->gym->id]);

        foreach ($members as $member) {
            $this->agent()->getJson($this->url('members/lookup?phone='.urlencode('+49 170 '.substr($member->phone, 5))))
                ->assertOk()
                ->assertJsonPath('data.match', 'unique')
                ->assertJsonPath('data.members.0.id', $member->id);
        }

        $this->assertSame($members->last()->id, AgentApiLog::latest('id')->first()->member_id);
    }

    #[Test]
    public function a_foreign_number_does_not_match_a_german_area_code(): void
    {
        Member::factory()->create(['gym_id' => $this->gym->id, 'phone' => '04366 4123456']);

        foreach (['0043 664 123456', '+43 664 123456'] as $phone) {
            $this->agent()->getJson($this->url('members/lookup?phone='.urlencode($phone)))
                ->assertOk()
                ->assertJsonPath('data.match', 'none');
        }

        $this->agent()->getJson($this->url('members/lookup?phone='.urlencode('0049 4366 4123456')))
            ->assertOk()
            ->assertJsonPath('data.match', 'unique');
    }

    #[Test]
    public function the_contract_carries_the_dates_the_portal_itself_computes(): void
    {
        $data = $this->agent()->getJson($this->url("members/{$this->member->id}/contract"))
            ->assertOk()->json('data');

        $fresh = $this->membership->fresh();

        $this->assertSame($this->membership->id, $data['active_membership']['id']);
        $this->assertSame('Flex 12', $data['active_membership']['plan']['name']);
        $this->assertSame(12, $data['active_membership']['plan']['commitment_months']);
        $this->assertFalse($data['active_membership']['cancellation']['is_cancelled']);
        $this->assertNotNull($data['active_membership']['cancellation']['next_possible_date']);
        $this->assertSame(
            $fresh->nextPossibleCancellationDate(),
            $data['active_membership']['cancellation']['next_possible_date'],
        );
    }

    #[Test]
    public function access_explains_why_a_member_cannot_get_in(): void
    {
        $this->agent()->getJson($this->url("members/{$this->member->id}/access"))
            ->assertOk()->assertJsonPath('data.should_have_access', true);

        $this->member->update(['status' => 'inactive']);
        $this->membership->update(['status' => 'expired']);

        $data = $this->agent()->getJson($this->url("members/{$this->member->id}/access"))->assertOk()->json('data');

        $this->assertFalse($data['should_have_access']);
        $this->assertEqualsCanonicalizing(
            ['member_not_active', 'no_active_membership'],
            array_column($data['blockers'], 'code'),
        );
    }

    #[Test]
    public function occupancy_gives_no_recommendation_without_enough_visits(): void
    {
        CheckIn::factory()->count(3)->create(['gym_id' => $this->gym->id, 'member_id' => $this->member->id, 'check_in_time' => now()->subDay()]);
        CheckIn::factory()->count(5)->create(['gym_id' => $this->otherGym->id, 'member_id' => $this->stranger->id, 'check_in_time' => now()->subDay()]);

        $this->agent()->getJson($this->url('occupancy'))
            ->assertOk()
            ->assertJsonPath('data.visits', 3)
            ->assertJsonPath('data.data_quality', 'insufficient_data')
            ->assertJsonPath('data.quietest_hour_by_weekday', null);
    }

    #[Test]
    public function inactive_members_lists_who_has_not_shown_up(): void
    {
        $regular = Member::factory()->create(['gym_id' => $this->gym->id, 'status' => 'active']);
        Membership::factory()->create(['member_id' => $regular->id, 'membership_plan_id' => $this->plan->id]);
        CheckIn::factory()->create(['gym_id' => $this->gym->id, 'member_id' => $regular->id, 'check_in_time' => now()->subDays(2)]);
        CheckIn::factory()->create(['gym_id' => $this->gym->id, 'member_id' => $this->member->id, 'check_in_time' => now()->subDays(45)]);

        $data = $this->agent()->getJson($this->url('insights/inactive-members?days=30'))->assertOk()->json('data');

        $this->assertSame([$this->member->id], array_column($data['members'], 'id'));
        $this->assertSame(45, $data['members'][0]['days_since_last_visit']);
    }

    #[Test]
    public function stats_hide_money_without_the_payments_ability(): void
    {
        $this->agent()->getJson($this->url('stats'))->assertOk()->assertJsonStructure(['data' => ['main_stats', 'financial_stats']]);

        $full = json_encode($this->agent()->getJson($this->url('stats'))->json('data'), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('revenue', $full);

        $noMoney = $this->issue(['stats.read']);
        $data = $this->agent($noMoney)->getJson($this->url('stats'))->assertOk()->json('data');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->assertArrayNotHasKey('financial_stats', $data);
        $this->assertStringNotContainsString('revenue', $json);
        // The only amounts left are the list prices of the plans, which plans.read shows anyway.
        $this->assertSame(count($data['plan_stats']), substr_count($json, '€'));
        $this->assertNotEmpty($data['main_stats']);
    }

    #[Test]
    public function during_a_free_period_the_contract_is_the_paid_membership(): void
    {
        $freePlan = MembershipPlan::factory()->create(['gym_id' => $this->gym->id, 'is_free_trial_plan' => true]);
        $member = Member::factory()->create(['gym_id' => $this->gym->id, 'status' => 'active']);
        $free = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $freePlan->id,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);
        $paid = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $this->plan->id,
            'start_date' => now()->addDays(11)->toDateString(),
            'status' => 'active',
            'linked_free_membership_id' => $free->id,
        ]);

        $data = $this->agent()->getJson($this->url("members/{$member->id}/contract"))->assertOk()->json('data');

        $this->assertSame($free->id, $data['active_membership']['id']);
        $this->assertSame($paid->id, $data['contract_membership']['id']);
    }

    // --- audit ----------------------------------------------------------

    #[Test]
    public function every_request_is_logged_and_reads_change_nothing(): void
    {
        $response = $this->agent()->getJson($this->url("members/{$this->member->id}/contract"))->assertOk();
        $this->agent()->getJson($this->url('plans'))->assertOk();
        $this->agent($this->issue(['plans.read']))->getJson($this->url('members'))->assertStatus(403);

        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));

        $logs = AgentApiLog::orderBy('id')->get();

        $this->assertCount(3, $logs);
        $this->assertSame([false, false, false], $logs->pluck('changed_data')->all());
        $this->assertSame([200, 200, 403], $logs->pluck('status')->all());
        $this->assertSame($this->member->id, $logs[0]->member_id);
        $this->assertSame('contracts.read', $logs[0]->ability);
        $this->assertNull($logs[0]->changes);
        $this->assertTrue($logs->every(fn ($log) => $log->gym_id === $this->gym->id));
    }

    #[Test]
    public function the_artisan_command_issues_a_working_token(): void
    {
        $this->artisan('agent:token', [
            'action' => 'create',
            '--gym' => (string) $this->gym->id,
            '--name' => 'Kommandozeile',
            '--purpose' => 'Test',
            '--abilities' => 'read',
        ])->assertSuccessful();

        $client = GymApiClient::where('name', 'Kommandozeile')->firstOrFail();

        $this->assertSame(GymApiClient::READ_ABILITIES, $client->abilities);
        $this->assertFalse($client->can('contracts.write'));

        $this->artisan('agent:token', ['action' => 'create', '--gym' => (string) $this->gym->id, '--name' => 'Ohne Zweck'])
            ->assertFailed();
    }

    // --- description -----------------------------------------------------

    #[Test]
    public function the_openapi_description_and_the_mcp_server_follow_the_routes(): void
    {
        $prefix = '/api/v1/agent';

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'v1.agent.'))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn (string $method) => $method === 'HEAD')
                ->map(fn (string $method) => strtolower($method).' /'.Str::after('/'.$route->uri(), $prefix.'/')))
            ->sort()->values()->all();

        $spec = Yaml::parseFile(base_path('agent-api/openapi.yaml'));

        $documented = collect($spec['paths'])
            ->flatMap(fn (array $operations, string $path) => collect(array_keys($operations))
                ->intersect(['get', 'post', 'put', 'patch', 'delete'])
                ->map(fn (string $method) => $method.' '.$path))
            ->sort()->values()->all();

        $this->assertNotEmpty($routes);
        $this->assertSame($prefix, $spec['servers'][0]['url']);
        $this->assertSame($routes, $documented);
        $this->assertStringContainsString('${BASE}'.$prefix.'/', file_get_contents(base_path('agent-api/mcp/server.mjs')));
    }
}
