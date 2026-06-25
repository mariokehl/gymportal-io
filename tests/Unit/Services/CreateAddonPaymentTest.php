<?php

namespace Tests\Unit\Services;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAddonPaymentTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaymentService::class);
        Carbon::setTestNow('2026-06-25');
    }

    private function makeMembership(Gym $gym, Member $member): Membership
    {
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);

        return Membership::factory()->create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => Carbon::today()->toDateString(),
            'status' => 'pending',
        ]);
    }

    public function test_it_uses_the_fixed_addon_payment_method(): void
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $membership = $this->makeMembership($gym, $member);

        $default = PaymentMethod::create([
            'member_id' => $member->id,
            'type' => 'sepa_direct_debit',
            'status' => 'active',
            'is_default' => true,
        ]);

        $addon = Addon::factory()->create([
            'gym_id' => $gym->id,
            'name' => 'Trainereinweisung',
            'price' => 29.90,
            'payment_method' => 'cash',
        ]);

        $payment = $this->service->createAddonPayment($member, $membership, $addon, $default);

        $this->assertNotNull($payment);
        $this->assertSame('cash', $payment->payment_method);
        $this->assertSame('29.90', (string) $payment->amount);
        $this->assertSame('addon', $payment->metadata['payment_type']);
        $this->assertSame($addon->id, $payment->metadata['addon_id']);
        $this->assertSame($membership->start_date->toDateString(), $payment->due_date->toDateString());
    }

    public function test_it_falls_back_to_member_default_payment_method(): void
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $membership = $this->makeMembership($gym, $member);

        $default = PaymentMethod::create([
            'member_id' => $member->id,
            'type' => 'sepa_direct_debit',
            'status' => 'active',
            'is_default' => true,
        ]);

        $addon = Addon::factory()->create([
            'gym_id' => $gym->id,
            'price' => 15.00,
            'payment_method' => null,
        ]);

        $payment = $this->service->createAddonPayment($member, $membership, $addon, $default);

        $this->assertSame('sepa_direct_debit', $payment->payment_method);
        $this->assertSame($default->id, $payment->metadata['payment_method_id']);
    }

    public function test_it_returns_null_when_start_date_is_in_the_past(): void
    {
        $gym = Gym::factory()->create();
        $member = Member::factory()->create(['gym_id' => $gym->id]);
        $membership = $this->makeMembership($gym, $member);
        $membership->update(['start_date' => Carbon::today()->subDay()->toDateString()]);
        $membership->refresh();

        $addon = Addon::factory()->create(['gym_id' => $gym->id, 'price' => 10]);

        $this->assertNull(
            $this->service->createAddonPayment($member, $membership, $addon, null)
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
