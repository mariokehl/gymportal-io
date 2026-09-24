<?php

namespace Tests\Feature\Models;

use App\Models\Gym;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the query scopes deciding which gyms the scheduled processes handle:
 * a gym needs a running trial or an active subscription.
 */
class GymActiveAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 03:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeGym(array $attributes): Gym
    {
        return Gym::factory()->create(array_merge([
            'subscription_status' => 'inactive',
            'subscription_ends_at' => null,
            'trial_ends_at' => now()->subDay(),
        ], $attributes));
    }

    private function assertAccess(Gym $gym, bool $expected): void
    {
        $this->assertSame($expected, Gym::withActiveAccess()->whereKey($gym->id)->exists());
        $this->assertSame(! $expected, Gym::withoutActiveAccess()->whereKey($gym->id)->exists());
        $this->assertSame($expected, $gym->fresh()->canAccessPremiumFeatures());
    }

    #[Test]
    public function a_gym_in_its_trial_has_access(): void
    {
        $this->assertAccess($this->makeGym(['trial_ends_at' => now()->addDay()]), true);
    }

    #[Test]
    public function a_gym_with_an_active_subscription_has_access(): void
    {
        $this->assertAccess($this->makeGym([
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ]), true);
    }

    #[Test]
    public function an_active_subscription_stays_valid_during_the_grace_period(): void
    {
        $this->assertAccess($this->makeGym([
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->subHour(),
        ]), true);
    }

    #[Test]
    public function a_gym_with_an_expired_trial_and_no_subscription_has_no_access(): void
    {
        $this->assertAccess($this->makeGym([]), false);
    }

    #[Test]
    public function a_gym_without_any_trial_date_or_subscription_has_no_access(): void
    {
        $gym = $this->makeGym([]);
        $gym->forceFill(['trial_ends_at' => null])->saveQuietly();

        $this->assertAccess($gym, false);
    }

    #[Test]
    public function a_subscription_past_the_grace_period_grants_no_access(): void
    {
        $this->assertAccess($this->makeGym([
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->subHours(3),
        ]), false);
    }

    #[Test]
    public function an_inactive_subscription_grants_no_access(): void
    {
        $this->assertAccess($this->makeGym([
            'subscription_status' => 'cancelled',
            'subscription_ends_at' => now()->addMonth(),
        ]), false);
    }
}
