<?php

namespace Tests\Feature\Web;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberDevice;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Route model binding resolves {member} and its nested models globally, without
 * a tenant scope. Every authenticated operator route that takes a {member} must
 * therefore reject a member of another gym itself. This test discovers those
 * routes from the router, so new routes are covered automatically.
 */
class MemberRouteTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        // A route that wrongly passes must not reach real mail or payment APIs.
        Mail::fake();
        Notification::fake();
        Bus::fake();
        Http::fake();

        $this->ownerRoleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
    }

    #[Test]
    public function every_member_route_rejects_a_member_of_another_gym(): void
    {
        $this->assertNoViolations(fn (array $own, array $foreign) => $foreign);
    }

    #[Test]
    public function every_member_route_rejects_a_foreign_member_combined_with_own_records(): void
    {
        // Catches actions that only authorize the nested record and then act on
        // the bound member, e.g. resetting all of its payment methods.
        $this->assertNoViolations(fn (array $own, array $foreign) => ['member' => $foreign['member']] + $own);
    }

    #[Test]
    public function every_member_route_rejects_foreign_records_under_an_own_member(): void
    {
        $this->assertNoViolations(
            fn (array $own, array $foreign) => ['member' => $own['member']] + $foreign,
            onlyNested: true,
        );
    }

    #[Test]
    public function the_fixtures_are_accepted_for_the_own_gym(): void
    {
        // Control: a 403 or 404 above only proves isolation if the same request
        // succeeds for the owner of the records.
        $expectedForOwner = [
            // No contract document has been generated for the fixture membership.
            'GET /members/{member}/documents/{membership}/download' => 404,
        ];

        $violations = [];

        foreach ($this->memberRoutes() as $route) {
            $method = collect($route->methods())->first(fn (string $method) => $method !== 'HEAD');
            $label = $method.' /'.$route->uri();

            $owner = $this->owner();
            $models = $this->modelsFor($owner);

            $uri = $route->uri();

            foreach ($route->parameterNames() as $name) {
                $uri = str_replace('{'.$name.'}', (string) $models[$name]->getRouteKey(), $uri);
            }

            $status = $this->actingAs($owner)->call($method, '/'.$uri)->getStatusCode();

            if (isset($expectedForOwner[$label])) {
                if ($status !== $expectedForOwner[$label]) {
                    $violations[] = "{$label}: expected {$expectedForOwner[$label]} for the owner, got {$status}";
                }

                continue;
            }

            if (in_array($status, [403, 404, 500], true)) {
                $violations[] = "{$label}: rejected the owner with {$status}";
            }
        }

        $this->assertSame([], $violations, "Fixtures not accepted for the own gym:\n".implode("\n", $violations));
    }

    /**
     * Calls every operator route that takes a {member} as an attacker and
     * collects all routes that do not answer with 403 or 404.
     *
     * @param  callable(array<string, Model>, array<string, Model>): array<string, Model>  $scenario
     *                                                                                                Picks the route parameters from the attacker's and the victim's records.
     */
    private function assertNoViolations(callable $scenario, bool $onlyNested = false): void
    {
        $routes = collect($this->memberRoutes())
            ->when($onlyNested, fn ($routes) => $routes->filter(fn (RoutingRoute $route) => count($route->parameterNames()) > 1))
            ->all();

        $this->assertNotEmpty($routes, 'No operator routes with a {member} parameter were found.');

        $violations = [];

        foreach ($routes as $route) {
            $method = collect($route->methods())->first(fn (string $method) => $method !== 'HEAD');
            $label = $method.' /'.$route->uri();

            // Fresh fixtures per route, so a vulnerable route cannot hide later ones
            // by deleting or changing shared records.
            $attacker = $this->owner();
            $models = $scenario($this->modelsFor($attacker), $this->modelsFor($this->owner()));

            $uri = $route->uri();

            foreach ($route->parameterNames() as $name) {
                if (! isset($models[$name])) {
                    $violations[] = "{$label}: no fixture for {{$name}}, extend modelsFor()";

                    continue 2;
                }

                $uri = str_replace('{'.$name.'}', (string) $models[$name]->getRouteKey(), $uri);
            }

            $status = $this->actingAs($attacker)->call($method, '/'.$uri)->getStatusCode();

            if (! in_array($status, [403, 404], true)) {
                $violations[] = "{$label}: expected 403 or 404, got {$status}";
            }
        }

        $this->assertSame([], $violations, "Routes reachable across gyms:\n".implode("\n", $violations));
    }

    /**
     * @return list<RoutingRoute>
     */
    private function memberRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => in_array('member', $route->parameterNames(), true))
            ->filter(fn (RoutingRoute $route) => in_array('auth:web', $route->gatherMiddleware(), true))
            ->values()
            ->all();
    }

    private function owner(): User
    {
        $owner = User::factory()->create(['role_id' => $this->ownerRoleId]);
        $gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $gym->id]);

        return $owner->fresh();
    }

    /**
     * Builds a complete set of models belonging to the owner's gym, keyed by
     * route parameter name.
     *
     * @return array<string, Model>
     */
    private function modelsFor(User $owner): array
    {
        $gymId = $owner->current_gym_id;

        $member = Member::factory()->create(['gym_id' => $gymId]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gymId]);
        $membership = Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
        ]);

        $addon = Addon::factory()->create(['gym_id' => $gymId]);
        $membership->addons()->attach($addon->id);

        $paymentMethod = PaymentMethod::create([
            'member_id' => $member->id,
            'type' => 'sepa_direct_debit',
            // Expired, so the owner may delete it and a 403 on DELETE is meaningful.
            'status' => 'expired',
            'requires_mandate' => true,
            'iban' => 'DE02120300000000202051',
        ]);

        $payment = Payment::create([
            'gym_id' => $gymId,
            'member_id' => $member->id,
            'membership_id' => $membership->id,
            'amount' => 49.90,
            'currency' => 'EUR',
            'description' => 'Beitrag',
            'due_date' => now(),
            'status' => 'pending',
        ]);

        return [
            'member' => $member,
            'membership' => $membership,
            'addon' => $addon,
            'paymentMethod' => $paymentMethod,
            'payment' => $payment,
            'device' => MemberDevice::factory()->create(['member_id' => $member->id]),
        ];
    }
}
