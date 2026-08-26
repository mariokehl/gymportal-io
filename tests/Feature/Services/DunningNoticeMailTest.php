<?php

namespace Tests\Feature\Services;

use App\Mail\DunningNoticeMail;
use App\Models\DunningNotice;
use App\Models\EmailTemplate;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Services\DunningService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DunningNoticeMailTest extends TestCase
{
    use RefreshDatabase;

    private DunningService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->service = app(DunningService::class);
    }

    private function memberWithOverduePayment(Gym $gym, int $daysOverdue = 30): Member
    {
        $member = Member::factory()->create([
            'gym_id' => $gym->id,
            'status' => 'active',
            'email' => 'mitglied@example.test',
        ]);

        Payment::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'amount' => 49.99,
            'currency' => 'EUR',
            'description' => 'Mitgliedsbeitrag',
            'status' => 'pending',
            'due_date' => Carbon::today()->subDays($daysOverdue),
        ]);

        return $member->fresh();
    }

    private function template(Gym $gym, string $type, string $subject, string $body): EmailTemplate
    {
        return EmailTemplate::create([
            'gym_id' => $gym->id,
            'name' => 'Eigene Vorlage',
            'type' => $type,
            'subject' => $subject,
            'body' => $body,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function test_reaching_a_level_mails_the_member(): void
    {
        $gym = Gym::factory()->create();
        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym);

        Mail::assertSent(DunningNoticeMail::class, fn (DunningNoticeMail $mail) => $mail->hasTo('mitglied@example.test')
            && $mail->level === DunningNotice::LEVEL_REMINDER);
    }

    public function test_a_dry_run_never_mails_the_member(): void
    {
        $gym = Gym::factory()->create();
        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym, dryRun: true);

        Mail::assertNothingSent();
    }

    public function test_it_uses_the_configured_template_of_the_level(): void
    {
        $gym = Gym::factory()->create();
        $this->template(
            $gym,
            'dunning_level_1',
            'Eigener Betreff für [Vorname]',
            '<p>Offen: [Offener-Betrag] EUR bis [Zahlungsfrist]</p>',
        );

        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym);

        Mail::assertSent(DunningNoticeMail::class, function (DunningNoticeMail $mail) use ($member) {
            $rendered = $mail->render();

            return str_contains($mail->envelope()->subject, "Eigener Betreff für {$member->first_name}")
                && str_contains($rendered, '49,99')
                && str_contains($rendered, Carbon::today()->addDays(Gym::DEFAULT_PAYMENT_PERIOD_DAYS)->format('d.m.Y'));
        });
    }

    public function test_without_a_template_the_default_wording_is_used(): void
    {
        $gym = Gym::factory()->create();
        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym);

        Mail::assertSent(DunningNoticeMail::class, function (DunningNoticeMail $mail) use ($gym) {
            return $mail->envelope()->subject === "Zahlungserinnerung - {$gym->name}"
                && str_contains($mail->render(), 'sicher ist es Ihnen entgangen');
        });
    }

    public function test_an_inactive_template_falls_back_to_the_default(): void
    {
        $gym = Gym::factory()->create();
        $template = $this->template($gym, 'dunning_level_1', 'Inaktiv', '<p>Inaktiv</p>');
        $template->update(['is_active' => false]);

        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym);

        Mail::assertSent(DunningNoticeMail::class, fn (DunningNoticeMail $mail) => $mail->envelope()->subject === "Zahlungserinnerung - {$gym->name}");
    }

    public function test_a_template_of_another_level_is_not_used(): void
    {
        $gym = Gym::factory()->create();
        // Only level 3 is customised, the first level must stay on its default.
        $this->template($gym, 'dunning_level_3', 'Letzte Aufforderung', '<p>Letzte Aufforderung</p>');

        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym);

        Mail::assertSent(DunningNoticeMail::class, fn (DunningNoticeMail $mail) => $mail->envelope()->subject === "Zahlungserinnerung - {$gym->name}");
    }

    public function test_a_template_of_another_gym_is_not_used(): void
    {
        $gym = Gym::factory()->create();
        $otherGym = Gym::factory()->create();
        $this->template($otherGym, 'dunning_level_1', 'Fremde Vorlage', '<p>Fremd</p>');

        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym);

        Mail::assertSent(DunningNoticeMail::class, fn (DunningNoticeMail $mail) => $mail->envelope()->subject === "Zahlungserinnerung - {$gym->name}");
    }

    public function test_the_payment_period_of_the_level_is_used(): void
    {
        $gym = Gym::factory()->create();
        $levels = $gym->inkasso_settings['levels'];
        $levels[0]['payment_period_days'] = 5;
        $gym->update(['inkasso_settings' => array_merge($gym->inkasso_settings, ['levels' => $levels])]);
        $gym = $gym->fresh();

        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym);

        Mail::assertSent(DunningNoticeMail::class, fn (DunningNoticeMail $mail) => $mail->dunningData['[Zahlungsfrist]'] === Carbon::today()->addDays(5)->format('d.m.Y'));
    }

    public function test_settings_without_a_payment_period_fall_back_to_the_default(): void
    {
        $gym = Gym::factory()->create();

        // Mimic settings stored before the option existed.
        $levels = array_map(function (array $level) {
            unset($level['payment_period_days']);

            return $level;
        }, $gym->inkasso_settings['levels']);

        $gym->update(['inkasso_settings' => array_merge($gym->inkasso_settings, ['levels' => $levels])]);
        $gym = $gym->fresh();

        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym);

        Mail::assertSent(DunningNoticeMail::class, fn (DunningNoticeMail $mail) => $mail->dunningData['[Zahlungsfrist]'] === Carbon::today()->addDays(Gym::DEFAULT_PAYMENT_PERIOD_DAYS)->format('d.m.Y'));
    }

    public function test_each_level_can_carry_its_own_period(): void
    {
        $gym = Gym::factory()->create();
        $levels = $gym->inkasso_settings['levels'];
        $levels[0]['payment_period_days'] = 21;
        $levels[1]['payment_period_days'] = 7;
        $gym->update(['inkasso_settings' => array_merge($gym->inkasso_settings, ['levels' => $levels])]);
        $gym = $gym->fresh();

        $member = $this->memberWithOverduePayment($gym);

        $this->service->escalate($member, $gym);
        $firstDeadline = Carbon::today()->addDays(21)->format('d.m.Y');

        Carbon::setTestNow(Carbon::now()->addDays(30));
        $this->service->escalate($member->fresh(), $gym);
        $secondDeadline = Carbon::today()->addDays(7)->format('d.m.Y');
        Carbon::setTestNow();

        Mail::assertSent(DunningNoticeMail::class, fn (DunningNoticeMail $mail) => $mail->level === DunningNotice::LEVEL_REMINDER
            && $mail->dunningData['[Zahlungsfrist]'] === $firstDeadline);

        Mail::assertSent(DunningNoticeMail::class, fn (DunningNoticeMail $mail) => $mail->level === DunningNotice::LEVEL_FIRST_NOTICE
            && $mail->dunningData['[Zahlungsfrist]'] === $secondDeadline);
    }

    public function test_the_second_level_reports_the_fee_separately(): void
    {
        $gym = Gym::factory()->create();
        $member = $this->memberWithOverduePayment($gym);

        // Level 1 first, then level 2 which books a fee of 5.00.
        $this->service->escalate($member, $gym);
        Carbon::setTestNow(Carbon::now()->addDays(30));
        $this->service->escalate($member->fresh(), $gym);
        Carbon::setTestNow();

        Mail::assertSent(DunningNoticeMail::class, function (DunningNoticeMail $mail) {
            if ($mail->level !== DunningNotice::LEVEL_FIRST_NOTICE) {
                return false;
            }

            return $mail->dunningData['[Offener-Betrag]'] === '49,99'
                && $mail->dunningData['[Mahngebuehr]'] === '5,00'
                && $mail->dunningData['[Gesamtbetrag]'] === '54,99';
        });
    }
}
