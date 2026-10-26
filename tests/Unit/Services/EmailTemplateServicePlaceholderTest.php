<?php

namespace Tests\Unit\Services;

use App\Models\Gym;
use App\Models\Member;
use App\Services\EmailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTemplateServicePlaceholderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_the_gym_email_separately_from_the_member_email(): void
    {
        $gym = Gym::factory()->create(['email' => 'info@studio.test']);
        $member = Member::factory()->create(['gym_id' => $gym->id, 'email' => 'member@example.test']);

        $data = app(EmailTemplateService::class)->buildPlaceholderData($gym, $member);

        $this->assertSame('info@studio.test', $data['[Studio-E-Mail]']);
        $this->assertSame('member@example.test', $data['[E-Mail]']);
    }
}
