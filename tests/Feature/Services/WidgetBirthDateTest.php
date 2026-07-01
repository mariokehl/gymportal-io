<?php

namespace Tests\Feature\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\WidgetService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WidgetBirthDateTest extends TestCase
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

    private function registrationData(MembershipPlan $plan, array $overrides = []): array
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

    public function test_member_is_created_with_null_birth_date_when_omitted(): void
    {
        $gym = Gym::factory()->create(['contracts_start_first_of_month' => false]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);

        $result = $this->service->processRegistration(
            $gym,
            $this->registrationData($plan, ['birth_date' => null])
        );

        $membership = Membership::findOrFail($result['membership']['id']);
        $member = Member::findOrFail($membership->member_id);

        // An empty birth date must be stored as null, not silently coerced to "today".
        $this->assertNull($member->birth_date);
    }

    public function test_member_is_created_with_provided_birth_date(): void
    {
        $gym = Gym::factory()->create(['contracts_start_first_of_month' => false]);
        $plan = MembershipPlan::factory()->create(['gym_id' => $gym->id]);

        $result = $this->service->processRegistration(
            $gym,
            $this->registrationData($plan, ['birth_date' => '1985-07-15'])
        );

        $membership = Membership::findOrFail($result['membership']['id']);
        $member = Member::findOrFail($membership->member_id);

        $this->assertSame('1985-07-15', $member->birth_date->format('Y-m-d'));
    }
}
