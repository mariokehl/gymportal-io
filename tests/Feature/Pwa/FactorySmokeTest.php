<?php

namespace Tests\Feature\Pwa;

use App\Models\Gym;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function gym_factory_creates_pwa_enabled_gym(): void
    {
        $gym = Gym::factory()->create();

        $this->assertTrue($gym->pwa_enabled);
        $this->assertTrue($gym->isPwaEnabled());
        $this->assertFalse($gym->isPwaLoginDisabled());
    }

    #[Test]
    public function member_factory_creates_active_member(): void
    {
        $member = Member::factory()->create();

        $this->assertSame('active', $member->status);
        $this->assertNotNull($member->gym_id);
    }
}
