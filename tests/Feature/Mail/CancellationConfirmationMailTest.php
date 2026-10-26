<?php

namespace Tests\Feature\Mail;

use App\Mail\CancellationConfirmationMail;
use App\Models\EmailTemplate;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\EmailTemplateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * § 312k (4) BGB: the confirmation of a cancellation has to state its content,
 * the moment it was received and when the membership ends.
 */
class CancellationConfirmationMailTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = Gym::factory()->create();
        $this->member = Member::factory()->create(['gym_id' => $this->gym->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function cancelledMembership(array $overrides = []): Membership
    {
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Premium Flex',
        ]);

        return Membership::factory()->create(array_merge([
            'member_id' => $this->member->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'cancellation_date' => '2027-03-31',
            'cancellation_reason' => 'Umzug',
            'cancellation_received_at' => '2026-10-26 14:35:12',
        ], $overrides));
    }

    private function createTemplate(string $body): void
    {
        EmailTemplate::create([
            'gym_id' => $this->gym->id,
            'name' => 'Kündigung',
            'type' => 'cancellation',
            'subject' => 'Kündigung [Vertragsnummer]',
            'body' => $body,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    #[Test]
    public function it_states_the_receipt_and_the_end_of_the_cancelled_membership(): void
    {
        // The mail is rendered days after the cancellation was received.
        Carbon::setTestNow('2026-10-29 09:00:00');
        $membership = $this->cancelledMembership();

        $this->createTemplate(
            '[Kuendigungseingang] | [Kuendigungstermin] | [Kuendigungsgrund] | [Tarif-Name] | [Vertragsnummer] | [Enddatum]'
        );

        $html = (new CancellationConfirmationMail($this->member, $membership, $this->gym))->render();

        $this->assertStringContainsString(
            '26.10.2026, 14:35 Uhr | 31.03.2027 | Umzug | Premium Flex | '.$membership->id.' | 31.03.2027',
            $html,
        );
    }

    #[Test]
    public function it_resolves_the_placeholders_after_an_immediate_cancellation(): void
    {
        // An immediately cancelled membership is no longer the active one,
        // which used to leave the contract placeholders empty.
        $membership = $this->cancelledMembership([
            'status' => 'cancelled',
            'end_date' => '2026-10-26',
            'cancellation_date' => '2026-10-26',
        ]);

        $this->createTemplate('Endet am [Kuendigungstermin], [Tarif-Name]');

        $html = (new CancellationConfirmationMail($this->member, $membership, $this->gym))->render();

        $this->assertStringContainsString('Endet am 26.10.2026, Premium Flex', $html);
    }

    #[Test]
    public function the_fallback_mail_states_the_receipt_as_well(): void
    {
        $membership = $this->cancelledMembership();

        $html = (new CancellationConfirmationMail($this->member, $membership, $this->gym))->render();

        $this->assertStringContainsString('26.10.2026, 14:35 Uhr', $html);
        $this->assertStringContainsString('31.03.2027', $html);
    }

    #[Test]
    public function the_default_template_contains_the_required_placeholders(): void
    {
        $body = app(EmailTemplateService::class)->getTemplateDefinitions()['cancellation']['body'];

        $this->assertStringContainsString('[Kuendigungseingang]', $body);
        $this->assertStringContainsString('[Kuendigungstermin]', $body);
        $this->assertStringContainsString('[Tarif-Name]', $body);
    }
}
