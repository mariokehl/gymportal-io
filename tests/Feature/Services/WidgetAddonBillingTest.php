<?php

namespace Tests\Feature\Services;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\WidgetService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WidgetAddonBillingTest extends TestCase
{
    use RefreshDatabase;

    private WidgetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-06-25');
        $this->service = app(WidgetService::class);
    }

    private function gymWithCashRegistration(): Gym
    {
        // Start memberships immediately so add-on payments are created.
        return Gym::factory()->create([
            'contracts_start_first_of_month' => false,
        ]);
    }

    private function baseRegistrationData(MembershipPlan $plan, array $overrides = []): array
    {
        return array_merge([
            'plan_id' => $plan->id,
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'email' => 'max'.uniqid().'@example.com',
            'phone' => '0123456789',
            'birth_date' => '1990-01-01',
            'address' => 'Teststr. 1',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'country' => 'DE',
            'payment_method' => 'cash',
            'widget_session' => 'sess-'.uniqid(),
            'selected_addons' => [],
        ], $overrides);
    }

    public function test_included_addon_is_attached_but_not_billed(): void
    {
        $gym = $this->gymWithCashRegistration();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $addon = Addon::factory()->create(['gym_id' => $gym->id, 'price' => 29.90]);
        $plan->addons()->attach($addon->id, ['mode' => 'included']);

        $result = $this->service->processRegistration($gym, $this->baseRegistrationData($plan));

        $membership = Membership::findOrFail($result['membership']['id']);

        // The included add-on is linked to the membership with a price snapshot of 0.
        $this->assertCount(1, $membership->addons);
        $pivot = $membership->addons->first()->pivot;
        $this->assertSame('included', $pivot->mode);
        $this->assertEquals(0, $pivot->price);
        $this->assertNull($pivot->payment_id);

        // No add-on payment is created for an included add-on.
        $addonPayment = $membership->payments()
            ->whereJsonContains('metadata->payment_type', 'addon')
            ->first();
        $this->assertNull($addonPayment);
    }

    public function test_optional_addon_is_only_billed_when_selected(): void
    {
        $gym = $this->gymWithCashRegistration();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $addon = Addon::factory()->create(['gym_id' => $gym->id, 'price' => 15.00]);
        $plan->addons()->attach($addon->id, ['mode' => 'optional']);

        // Not selected => not billed.
        $resultWithout = $this->service->processRegistration($gym, $this->baseRegistrationData($plan));
        $membershipWithout = Membership::findOrFail($resultWithout['membership']['id']);
        $this->assertCount(0, $membershipWithout->addons);

        // Selected => billed.
        $resultWith = $this->service->processRegistration(
            $gym,
            $this->baseRegistrationData($plan, ['selected_addons' => [$addon->id]])
        );
        $membershipWith = Membership::findOrFail($resultWith['membership']['id']);
        $this->assertCount(1, $membershipWith->addons);
        $this->assertSame('optional', $membershipWith->addons->first()->pivot->mode);

        // A selected optional add-on is billed with its full price.
        $addonPayment = $membershipWith->payments()
            ->whereJsonContains('metadata->payment_type', 'addon')
            ->first();
        $this->assertNotNull($addonPayment);
        $this->assertSame('15.00', (string) $addonPayment->amount);
    }

    public function test_optional_addon_of_another_plan_is_ignored(): void
    {
        $gym = $this->gymWithCashRegistration();
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);
        $foreignAddon = Addon::factory()->create(['gym_id' => $gym->id, 'price' => 99.00]);
        // Addon is NOT assigned to $plan, but the client tries to select it.

        $result = $this->service->processRegistration(
            $gym,
            $this->baseRegistrationData($plan, ['selected_addons' => [$foreignAddon->id]])
        );
        $membership = Membership::findOrFail($result['membership']['id']);

        $this->assertCount(0, $membership->addons);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
