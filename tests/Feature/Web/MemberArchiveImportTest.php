<?php

namespace Tests\Feature\Web;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\CreditLedgerService;
use App\Services\MemberArchiveImportService;
use App\Services\MemberArchiveParser;
use App\Services\MollieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class MemberArchiveImportTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/archive-import-'.bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);

        $roleId = Role::factory()->create(['name' => 'Gym Owner', 'slug' => 'owner'])->id;
        $owner = User::factory()->create(['role_id' => $roleId]);
        $this->gym = Gym::factory()->create(['owner_id' => $owner->id]);
        $owner->update(['current_gym_id' => $this->gym->id]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);

        parent::tearDown();
    }

    #[Test]
    public function it_imports_a_member_with_tariff_sepa_mandate_and_module(): void
    {
        $this->makeMemberFolder('9-101_Max Muster_Modul', [
            'primary' => [
                'Mitgliedsnummer' => '9-101',
                'Anrede' => 'Herr',
                'Vorname' => 'Max',
                'Nachname' => 'Muster',
                'E-Mail' => 'max.muster@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Flex-Tarif',
                'Preis' => "monatlich: 35,98\u{00A0}€",
                'Laufzeit' => '4M',
                'Kündigungsfrist' => '3M',
                'Verlängerungsdauer' => '24M',
                'Vertragsbeginn' => '01.04.2022',
                'Bezahlt bis' => '30.06.2026',
                'Mitgliedskarte' => '1000000001',
            ],
            'modules' => [[
                'Mitgliedsnummer' => '9-101',
                'Typ' => 'Modulvertrag',
                'Tarifname' => 'Getränke-Flatrate für Flex-Tarif',
                'Preis' => "monatlich: 8,62\u{00A0}€",
                'Vertragsbeginn' => '01.02.2023',
            ]],
            'bank_accounts' => [[
                'accountHolder' => 'Max Muster',
                'iban' => 'DE02120300000000202051',
                'bic' => 'BANKDEFFXXX',
                'bankName' => 'Testbank',
                'endDate' => null,
                'sepaMandateDtos' => [[
                    'sepaMandateStatus' => 'CONFIRMED',
                    'referenceNumber' => 'REF-100962',
                    'mandateGivenDate' => '2022-03-30',
                    'mandateWithdrawnDate' => null,
                ]],
            ]],
        ]);

        $stats = $this->importService()->import($this->gym->id, $this->folders());

        $this->assertSame([], $stats['errors']);
        $this->assertSame(1, $stats['members_created']);
        $this->assertSame(1, $stats['memberships_created']);

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $this->assertSame('Max', $member->first_name);
        // The original member number stays traceable in the notes.
        $this->assertStringContainsString('9-101', $member->notes);

        // The tariff is created from the contract terms of the export.
        $plan = MembershipPlan::where('gym_id', $this->gym->id)->firstOrFail();
        $this->assertSame('Flex-Tarif', $plan->name);
        $this->assertSame('35.98', (string) $plan->price);
        $this->assertSame(4, $plan->commitment_months);
        $this->assertSame(3, $plan->cancellation_period);
        $this->assertSame('months', $plan->cancellation_period_unit);

        // The SEPA mandate is taken over as active, so the member does not have
        // to sign a new one after the migration.
        $paymentMethod = PaymentMethod::where('member_id', $member->id)->firstOrFail();
        $this->assertSame('sepa_direct_debit', $paymentMethod->type);
        $this->assertSame('active', $paymentMethod->status);
        $this->assertSame('active', $paymentMethod->sepa_mandate_status);
        $this->assertSame('REF-100962', $paymentMethod->sepa_mandate_reference);
        $this->assertSame('DE02120300000000202051', $paymentMethod->iban);

        // The module contract becomes a recurring add-on booked on the membership.
        $addon = Addon::where('gym_id', $this->gym->id)->firstOrFail();
        $this->assertSame('Getränke-Flatrate für Flex-Tarif', $addon->name);
        $this->assertSame('usage', $addon->service_type);
        $this->assertSame('recurring', $addon->billing_type);
        $this->assertNull($addon->quota_amount);

        $membership = Membership::where('member_id', $member->id)->firstOrFail();
        $this->assertTrue($membership->addons()->where('addons.id', $addon->id)->exists());

        // The module keeps the date the previous system booked it on, so the
        // member is not shown the import day as the booking date.
        $booked = $membership->addons()->where('addons.id', $addon->id)->first();
        $this->assertSame('2023-02-01', $booked->pivot->booked_at);

        // The member card keeps working at the scanner.
        $config = MemberAccessConfig::where('member_id', $member->id)->firstOrFail();
        $this->assertSame('1000000001', $config->nfc_uid);
        $this->assertTrue((bool) $config->nfc_enabled);
    }

    #[Test]
    public function it_keeps_the_billing_day_of_a_lapsed_paid_period(): void
    {
        // Mirrors member M004260005: billed on the 1st since April 2022 and
        // collected up to 30.06.2026 by the previous system.
        $this->makeMemberFolder('1-6_Lapsed Period_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-6',
                'Vorname' => 'Lapsed',
                'Nachname' => 'Period',
                'E-Mail' => 'lapsed.period@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 35,98\u{00A0}€",
                'Vertragsbeginn' => '01.04.2022',
                'Bezahlt bis' => '30.06.2026',
            ],
            'bank_accounts' => [[
                'accountHolder' => 'Lapsed Period',
                'iban' => 'DE02120300000000202051',
                'bic' => 'BANKDEFFXXX',
                'bankName' => 'Testbank',
                'endDate' => null,
                'sepaMandateDtos' => [[
                    'sepaMandateStatus' => 'CONFIRMED',
                    'referenceNumber' => 'REF-200003',
                    'mandateGivenDate' => '2022-03-30',
                    'mandateWithdrawnDate' => null,
                ]],
            ]],
        ]);

        // Pin the clock so the collected period stays in the past whenever the
        // suite runs.
        $this->travelTo(Carbon::parse('2026-08-21'));

        // The gym collects four days ahead of the due date instead of the
        // default two.
        $this->gym->update([
            'payment_execution_settings' => [
                'sepa_direct_debit' => ['initial' => 3, 'recurring' => -4],
            ],
        ]);

        $this->importService()->import($this->gym->id, $this->folders());

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $payment = $member->payments()->firstOrFail();

        // The charge stays on the day after the collected period, even once
        // that day has passed, instead of being pulled onto today.
        $this->assertSame('2026-07-01', $payment->due_date->toDateString());

        // The handover continues the running series, so it uses the gym's
        // recurring offset rather than the one for a first payment.
        $this->assertSame('2026-06-27', $payment->execution_date->toDateString());

        // The member keeps seeing the familiar period-based wording instead of
        // being told this is their first contribution.
        $this->assertSame('recurring', $payment->metadata['payment_type']);
        $this->assertSame('Mitgliedsbeitrag 07/2026 - Basis', $payment->description);
        $this->assertStringNotContainsString('1. Mitgliedsbeitrag', $payment->description);
    }

    #[Test]
    public function it_starts_billing_after_the_period_the_previous_system_collected(): void
    {
        $this->makeMemberFolder('1-1_Paid Until_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-1',
                'Vorname' => 'Paid',
                'Nachname' => 'Until',
                'E-Mail' => 'paid.until@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 20,00\u{00A0}€",
                'Vertragsbeginn' => '01.01.2026',
                // The previous system already collected up to this date.
                'Bezahlt bis' => '31.12.2099',
            ],
        ]);

        $this->importService()->import($this->gym->id, $this->folders());

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $payment = $member->payments()->firstOrFail();

        // The first charge in gymportal.io covers the day after that period.
        $this->assertSame('2100-01-01', $payment->due_date->toDateString());
    }

    #[Test]
    public function it_does_not_charge_a_membership_that_already_ended(): void
    {
        $this->makeMemberFolder('1-2_Old Member_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-2',
                'Vorname' => 'Old',
                'Nachname' => 'Member',
                'E-Mail' => 'old.member@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 20,00\u{00A0}€",
                'Vertragsbeginn' => '01.01.2015',
                'Vertragsende' => '31.12.2017',
                'Bezahlt bis' => '31.12.2017',
            ],
            'bank_accounts' => [[
                'accountHolder' => 'Old Member',
                'iban' => 'DE02120300000000202051',
                'bic' => 'BANKDEFFXXX',
                'bankName' => 'Testbank',
                'endDate' => null,
                'sepaMandateDtos' => [[
                    'sepaMandateStatus' => 'CONFIRMED',
                    'referenceNumber' => 'REF-200001',
                    'mandateGivenDate' => '2015-01-01',
                    'mandateWithdrawnDate' => null,
                ]],
            ]],
        ]);

        $stats = $this->importService()->import($this->gym->id, $this->folders());

        $this->assertSame([], $stats['errors']);
        $this->assertSame(1, $stats['members_created']);
        // The archived member is imported, but nothing is billed any more.
        $this->assertSame(0, $stats['payments_created']);

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $this->assertSame(0, $member->payments()->count());

        $membership = Membership::where('member_id', $member->id)->firstOrFail();
        $this->assertSame('expired', $membership->status);

        // Without a running membership the member is archived as inactive.
        $this->assertSame('inactive', $member->status);

        // The mandate is retired so nothing stays collectable.
        $paymentMethod = PaymentMethod::where('member_id', $member->id)->firstOrFail();
        $this->assertSame('expired', $paymentMethod->status);
        $this->assertSame('expired', $paymentMethod->sepa_mandate_status);
    }

    #[Test]
    public function it_keeps_a_member_with_a_running_membership_active(): void
    {
        $this->makeMemberFolder('1-5_Running Member_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-5',
                'Vorname' => 'Running',
                'Nachname' => 'Member',
                'E-Mail' => 'running.member@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 20,00\u{00A0}€",
                'Vertragsbeginn' => '01.01.2026',
                'Bezahlt bis' => '31.12.2099',
            ],
            'bank_accounts' => [[
                'accountHolder' => 'Running Member',
                'iban' => 'DE02120300000000202051',
                'bic' => 'BANKDEFFXXX',
                'bankName' => 'Testbank',
                'endDate' => null,
                'sepaMandateDtos' => [[
                    'sepaMandateStatus' => 'CONFIRMED',
                    'referenceNumber' => 'REF-200002',
                    'mandateGivenDate' => '2026-01-01',
                    'mandateWithdrawnDate' => null,
                ]],
            ]],
        ]);

        $this->importService()->import($this->gym->id, $this->folders());

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $this->assertSame('active', $member->status);

        $paymentMethod = PaymentMethod::where('member_id', $member->id)->firstOrFail();
        $this->assertSame('active', $paymentMethod->status);
        $this->assertSame('active', $paymentMethod->sepa_mandate_status);
    }

    #[Test]
    public function it_does_not_charge_a_membership_cancelled_to_a_past_date(): void
    {
        $this->makeMemberFolder('1-3_Cancelled Member_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-3',
                'Vorname' => 'Cancelled',
                'Nachname' => 'Member',
                'E-Mail' => 'cancelled.member@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 20,00\u{00A0}€",
                'Vertragsbeginn' => '01.01.2015',
                'Gekündigt zum' => '30.06.2018',
                'Gekündigt am' => '31.03.2018',
                // The export can still carry a paid period beyond the end.
                'Bezahlt bis' => '31.12.2099',
            ],
        ]);

        $stats = $this->importService()->import($this->gym->id, $this->folders());

        $this->assertSame([], $stats['errors']);
        $this->assertSame(0, $stats['payments_created']);

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $membership = Membership::where('member_id', $member->id)->firstOrFail();
        $this->assertSame('expired', $membership->status);
        $this->assertSame(0, $member->payments()->count());
        $this->assertSame('inactive', $member->status);
    }

    #[Test]
    public function it_reports_an_ended_membership_in_the_analysis(): void
    {
        $this->makeMemberFolder('1-4_Ended Preview_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-4',
                'Vorname' => 'Ended',
                'Nachname' => 'Preview',
                'E-Mail' => 'ended.preview@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 20,00\u{00A0}€",
                'Vertragsbeginn' => '01.01.2015',
                'Vertragsende' => '31.12.2017',
                'Bezahlt bis' => '31.12.2017',
            ],
        ]);

        $result = $this->importService()->analyse($this->gym->id, $this->folders());

        // The dry run must promise exactly what the import will do.
        $this->assertTrue($result['members'][0]['membership_ended']);
        $this->assertNull($result['members'][0]['next_charge']);
        $this->assertNotEmpty(array_filter(
            $result['warnings'],
            fn (string $warning) => str_contains($warning, 'beendet')
        ));
    }

    #[Test]
    public function it_takes_the_mandate_over_into_mollie_when_it_overrides_sepa(): void
    {
        // The studio collects its direct debits through Mollie, so the standard
        // SEPA method is overridden.
        $this->gym->update([
            'mollie_config' => [
                'api_key' => 'test_key',
                'enabled_methods' => ['directdebit'],
            ],
        ]);

        $handled = [];
        $this->mock(MollieService::class, function ($mock) use (&$handled) {
            $mock->shouldReceive('handleMolliePaymentMethod')
                ->once()
                ->andReturnUsing(function (Member $member, PaymentMethod $paymentMethod) use (&$handled) {
                    $handled[] = $paymentMethod->type;

                    return true;
                });
        });

        $this->makeMemberFolder('1-7_Mollie Member_x', $this->sepaMemberSpec('1-7', 'Mollie', 'Member'));

        $this->importService()->import($this->gym->id, $this->folders());

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $paymentMethod = PaymentMethod::where('member_id', $member->id)->firstOrFail();

        // The method is created for the integration, not as an in-house debit.
        $this->assertSame('mollie_directdebit', $paymentMethod->type);
        $this->assertSame(['mollie_directdebit'], $handled);
    }

    #[Test]
    public function it_keeps_the_in_house_mandate_when_mollie_does_not_override_sepa(): void
    {
        // Mollie is configured, but not for direct debit.
        $this->gym->update([
            'mollie_config' => [
                'api_key' => 'test_key',
                'enabled_methods' => ['creditcard'],
            ],
        ]);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldNotReceive('handleMolliePaymentMethod');
        });

        $this->makeMemberFolder('1-8_Inhouse Member_x', $this->sepaMemberSpec('1-8', 'Inhouse', 'Member'));

        $this->importService()->import($this->gym->id, $this->folders());

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $paymentMethod = PaymentMethod::where('member_id', $member->id)->firstOrFail();

        $this->assertSame('sepa_direct_debit', $paymentMethod->type);
        $this->assertSame('active', $paymentMethod->sepa_mandate_status);
    }

    #[Test]
    public function it_still_imports_the_member_when_the_integration_rejects_the_mandate(): void
    {
        $this->gym->update([
            'mollie_config' => [
                'api_key' => 'test_key',
                'enabled_methods' => ['directdebit'],
            ],
        ]);

        $this->mock(MollieService::class, function ($mock) {
            $mock->shouldReceive('handleMolliePaymentMethod')
                ->andThrow(new \RuntimeException('Mollie is unreachable'));
        });

        $this->makeMemberFolder('1-9_Failing Member_x', $this->sepaMemberSpec('1-9', 'Failing', 'Member'));

        $stats = $this->importService()->import($this->gym->id, $this->folders());

        // A failing integration must not cost the whole member record.
        $this->assertSame([], $stats['errors']);
        $this->assertSame(1, $stats['members_created']);

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $this->assertSame(1, PaymentMethod::where('member_id', $member->id)->count());
    }

    #[Test]
    public function it_hands_the_original_mandate_details_to_the_integration(): void
    {
        $this->gym->update([
            'mollie_config' => [
                'api_key' => 'test_key',
                'enabled_methods' => ['directdebit'],
            ],
        ]);

        $seen = null;
        $this->mock(MollieService::class, function ($mock) use (&$seen) {
            $mock->shouldReceive('handleMolliePaymentMethod')
                ->andReturnUsing(function (Member $member, PaymentMethod $paymentMethod) use (&$seen) {
                    $seen = $paymentMethod;

                    return true;
                });
        });

        $this->makeMemberFolder('2-1_Detail Member_x', $this->sepaMemberSpec('2-1', 'Detail', 'Member'));

        $this->importService()->import($this->gym->id, $this->folders());

        // The mandate keeps the reference and signature date it was granted
        // under, so the member does not have to sign again after the handover.
        $this->assertNotNull($seen);
        $this->assertSame('REF-2-1', $seen->sepa_mandate_reference);
        $this->assertSame('2026-01-01', $seen->sepa_mandate_signed_at->toDateString());
        $this->assertSame('DE02120300000000202051', $seen->iban);
        $this->assertSame('Detail Member', $seen->account_holder);
    }

    /**
     * A member folder with a SEPA mandate, reduced to what these tests need.
     */
    private function sepaMemberSpec(string $number, string $firstName, string $lastName): array
    {
        return [
            'primary' => [
                'Mitgliedsnummer' => $number,
                'Vorname' => $firstName,
                'Nachname' => $lastName,
                'E-Mail' => strtolower($firstName).'.'.strtolower($lastName).'@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 20,00\u{00A0}€",
                'Vertragsbeginn' => '01.01.2026',
                'Bezahlt bis' => '31.12.2099',
            ],
            'bank_accounts' => [[
                'accountHolder' => $firstName.' '.$lastName,
                'iban' => 'DE02120300000000202051',
                'bic' => 'BANKDEFFXXX',
                'bankName' => 'Testbank',
                'endDate' => null,
                'sepaMandateDtos' => [[
                    'sepaMandateStatus' => 'CONFIRMED',
                    'referenceNumber' => 'REF-'.$number,
                    'mandateGivenDate' => '2026-01-01',
                    'mandateWithdrawnDate' => null,
                ]],
            ]],
        ];
    }

    #[Test]
    public function it_takes_over_the_account_balance_as_credit(): void
    {
        $this->makeMemberFolder('9-102_Credit Test_x', [
            'primary' => [
                'Mitgliedsnummer' => '9-102',
                'Vorname' => 'Credit',
                'Nachname' => 'Test',
                'E-Mail' => 'credit.test@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 20,00\u{00A0}€",
            ],
            'account_rows' => [
                ['Kontostand: ', '119.90'],
                ['Verzehrguthaben: ', '0.00'],
            ],
        ]);

        $stats = $this->importService()->import($this->gym->id, $this->folders());

        $this->assertSame(1, $stats['credit_entries_created']);

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $this->assertSame(11990, app(CreditLedgerService::class)->getBalance($member));
    }

    #[Test]
    public function it_reuses_an_existing_plan_instead_of_creating_a_duplicate(): void
    {
        $plan = MembershipPlan::factory()->create([
            'gym_id' => $this->gym->id,
            'name' => 'Flex-Tarif',
            'price' => 35.98,
        ]);

        $this->makeMemberFolder('1-5_Plan Match_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-5',
                'Vorname' => 'Plan',
                'Nachname' => 'Match',
                'E-Mail' => 'plan.match@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Flex-Tarif',
                'Preis' => "monatlich: 35,98\u{00A0}€",
            ],
        ]);

        $stats = $this->importService()->import($this->gym->id, $this->folders());

        $this->assertSame(0, $stats['plans_created']);
        $this->assertSame(1, MembershipPlan::where('gym_id', $this->gym->id)->count());

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $this->assertSame($plan->id, $member->memberships()->firstOrFail()->membership_plan_id);
    }

    #[Test]
    public function it_skips_a_member_that_was_already_imported(): void
    {
        $this->makeMemberFolder('1-7_Double Import_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-7',
                'Vorname' => 'Double',
                'Nachname' => 'Import',
                'E-Mail' => 'double.import@example.test',
                'Geburtstag' => '01.02.1990',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 20,00\u{00A0}€",
            ],
        ]);

        $this->importService()->import($this->gym->id, $this->folders());
        $stats = $this->importService()->import($this->gym->id, $this->folders());

        $this->assertSame(0, $stats['members_created']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertSame(1, Member::where('gym_id', $this->gym->id)->count());
    }

    #[Test]
    public function the_analysis_reports_what_would_be_imported_without_writing(): void
    {
        $this->makeMemberFolder('1-9_Preview Member_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-9',
                'Vorname' => 'Preview',
                'Nachname' => 'Member',
                'E-Mail' => 'preview.member@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Neuer Tarif',
                'Preis' => "monatlich: 30,00\u{00A0}€",
                'Bezahlt bis' => '30.06.2099',
            ],
        ]);

        $result = $this->importService()->analyse($this->gym->id, $this->folders());

        $this->assertTrue($result['valid']);
        $this->assertSame(1, $result['stats']['members']);
        $this->assertSame(1, $result['stats']['plans_new']);
        $this->assertSame(['Neuer Tarif'], $result['new_plans']);
        $this->assertSame('2099-07-01', $result['members'][0]['next_charge']);

        $this->assertSame(0, Member::where('gym_id', $this->gym->id)->count());
    }

    #[Test]
    public function a_folder_upload_is_staged_across_several_chunks(): void
    {
        $this->makeMemberFolder('1-3_Chunk Member_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-3',
                'Vorname' => 'Chunk',
                'Nachname' => 'Member',
                'E-Mail' => 'chunk.member@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Basis',
                'Preis' => "monatlich: 20,00\u{00A0}€",
            ],
            'bank_accounts' => [[
                'accountHolder' => 'Chunk Member',
                'iban' => 'DE02120300000000202051',
                'endDate' => null,
                'sepaMandateDtos' => [[
                    'sepaMandateStatus' => 'CONFIRMED',
                    'referenceNumber' => 'REF-CHUNK-1',
                    'mandateGivenDate' => '2026-01-15',
                    'mandateWithdrawnDate' => null,
                ]],
            ]],
        ]);

        $owner = User::find($this->gym->owner_id);
        $folder = $this->root.'/1-3_Chunk Member_x';

        // First chunk: only the bank details, without the master sheet.
        $first = $this->actingAs($owner)->post(route('data-transfer.upload-archive-chunk'), [
            'files' => [
                new UploadedFile(
                    $folder.'/bank_accounts.json',
                    '1-3_Chunk Member_x/bank_accounts.json',
                    null,
                    null,
                    true
                ),
            ],
        ]);

        $first->assertOk()->assertJson(['success' => true]);
        $token = $first->json('token');

        // Second chunk reuses the token and completes the member folder.
        $this->actingAs($owner)->post(route('data-transfer.upload-archive-chunk'), [
            'token' => $token,
            'files' => [
                new UploadedFile(
                    $folder.'/master_data.xlsx',
                    '1-3_Chunk Member_x/master_data.xlsx',
                    null,
                    null,
                    true
                ),
            ],
        ])->assertOk()->assertJson(['success' => true, 'token' => $token]);

        // Only after both chunks is the member folder complete and readable.
        $this->actingAs($owner)
            ->postJson(route('data-transfer.validate-archive'), ['token' => $token])
            ->assertOk()
            ->assertJson([
                'valid' => true,
                'stats' => ['members' => 1, 'sepa_mandates' => 1],
            ]);
    }

    #[Test]
    public function an_upload_can_be_analysed_and_imported_through_the_endpoints(): void
    {
        // A long cancelled contract still has to import cleanly.
        $this->makeMemberFolder('1-1_Old Member_x', [
            'primary' => [
                'Mitgliedsnummer' => '1-1',
                'Vorname' => 'Old',
                'Nachname' => 'Member',
                'E-Mail' => 'old.member@example.test',
                'Typ' => 'Vertrag',
                'Tarifname' => 'Alttarif 2016',
                'Preis' => "monatlich: 24,00\u{00A0}€",
                'Vertragsbeginn' => '01.11.2016',
                'Gekündigt zum' => '31.10.2017',
                'Gekündigt am' => '24.07.2017',
                'Vertragsende' => '31.10.2017',
                'Bezahlt bis' => '31.10.2017',
            ],
        ]);

        $owner = User::find($this->gym->owner_id);
        $folder = $this->root.'/1-1_Old Member_x';

        $upload = $this->actingAs($owner)->post(route('data-transfer.upload-archive-chunk'), [
            'files' => [
                new UploadedFile(
                    $folder.'/master_data.xlsx',
                    '1-1_Old Member_x/master_data.xlsx',
                    null,
                    null,
                    true
                ),
            ],
        ])->assertOk();

        $token = $this->actingAs($owner)
            ->postJson(route('data-transfer.validate-archive'), ['token' => $upload->json('token')])
            ->assertOk()
            ->assertJson(['valid' => true])
            ->json('token');

        $this->actingAs($owner)
            ->postJson(route('data-transfer.import-archive'), [
                'token' => $token,
                'fallback_start_date' => '2026-09-01',
                'create_missing_plans' => true,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'stats' => ['members_created' => 1, 'memberships_created' => 1],
            ]);

        $member = Member::where('gym_id', $this->gym->id)->firstOrFail();
        $membership = $member->memberships()->firstOrFail();

        // The cancellation of the old contract is carried over.
        $this->assertSame('expired', $membership->status);
        $this->assertSame('2017-10-31', $membership->end_date->toDateString());
    }

    #[Test]
    public function it_rejects_a_staging_token_that_tries_to_escape_the_upload_directory(): void
    {
        $owner = User::find($this->gym->owner_id);

        $this->actingAs($owner)
            ->postJson(route('data-transfer.validate-archive'), ['token' => '../../../etc'])
            ->assertStatus(422);
    }

    #[Test]
    public function it_rejects_an_upload_that_exceeds_the_total_size_limit(): void
    {
        $owner = User::find($this->gym->owner_id);
        $oversized = $this->makeSparseFile('oversized.zip', 101 * 1024 * 1024);

        $this->actingAs($owner)
            ->postJson(route('data-transfer.upload-archive-chunk'), [
                'files' => [new UploadedFile($oversized, 'oversized.zip', 'application/zip', null, true)],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function it_rejects_chunks_that_only_exceed_the_size_limit_in_sum(): void
    {
        $owner = User::find($this->gym->owner_id);

        // Each chunk on its own stays below the limit; only their sum exceeds it.
        $first = $this->actingAs($owner)->postJson(route('data-transfer.upload-archive-chunk'), [
            'files' => [
                new UploadedFile(
                    $this->makeSparseFile('part-1.bin', 60 * 1024 * 1024),
                    'akten/part-1.bin',
                    null,
                    null,
                    true
                ),
            ],
        ]);

        $first->assertOk();
        $token = $first->json('token');

        $this->actingAs($owner)
            ->postJson(route('data-transfer.upload-archive-chunk'), [
                'token' => $token,
                'files' => [
                    new UploadedFile(
                        $this->makeSparseFile('part-2.bin', 60 * 1024 * 1024),
                        'akten/part-2.bin',
                        null,
                        null,
                        true
                    ),
                ],
            ])
            ->assertStatus(422);

        // The staging directory is discarded so no partial upload is left behind.
        $this->assertDirectoryDoesNotExist(storage_path('app/tmp/'.$token));
    }

    #[Test]
    public function it_rejects_an_archive_upload_from_a_user_without_permission(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson(route('data-transfer.upload-archive-chunk'), [])
            ->assertForbidden();
    }

    /**
     * Create a sparse file of the given size: the bytes are reported by the
     * filesystem but barely occupy any disk space, which keeps the size-limit
     * tests fast.
     */
    private function makeSparseFile(string $name, int $bytes): string
    {
        $path = $this->root.'/'.$name;

        $handle = fopen($path, 'w');
        fseek($handle, $bytes - 1);
        fwrite($handle, "\0");
        fclose($handle);

        return $path;
    }

    private function importService(): MemberArchiveImportService
    {
        return app(MemberArchiveImportService::class);
    }

    /**
     * @return array<int, string>
     */
    private function folders(): array
    {
        return app(MemberArchiveParser::class)->findMemberFolders($this->root);
    }

    private function makeMemberFolder(string $name, array $spec): string
    {
        $folder = $this->root.'/'.$name;
        mkdir($folder, 0700, true);

        $columns = [
            'Mitgliedsnummer', 'Mitgliedskarte', 'Anrede', 'Vorname', 'Nachname', 'Straße', 'PLZ',
            'Ort', 'Land', 'Geburtstag', 'Telefon (privat)', 'E-Mail', 'Bankname', 'BIC', 'IBAN',
            'Kontoinhaber', 'SEPA-Mandatsreferenz-Nr.', 'Notizen', 'Typ', 'Tarifname', 'Laufzeit',
            'Kündigungsfrist', 'Verlängerungsdauer', 'Vertragsbeginn', 'Gekündigt zum', 'Gekündigt am',
            'Vertragsende', 'Zahlweise', 'Preis', 'Zahlungsmethode', 'Saldo', 'Verzehrguthabenausgleich',
            'Bezahlt bis', 'archiviert',
        ];

        $rows = [$columns];

        foreach (array_merge([$spec['primary']], $spec['modules'] ?? []) as $row) {
            $rows[] = array_map(fn ($column) => (string) ($row[$column] ?? ''), $columns);
        }

        $this->writeSheet($folder.'/master_data.xlsx', $rows);

        if (isset($spec['account_rows'])) {
            $this->writeSheet($folder.'/account_data.xlsx', $spec['account_rows']);
        }

        if (isset($spec['bank_accounts'])) {
            file_put_contents($folder.'/bank_accounts.json', json_encode($spec['bank_accounts']));
        }

        return $folder;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeSheet(string $path, array $rows): void
    {
        $xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $xml .= '<row r="'.($rowIndex + 1).'">';

            foreach (array_values($row) as $cellIndex => $value) {
                $xml .= '<c r="'.$this->columnLetter($cellIndex).($rowIndex + 1).'" t="inlineStr"><is><t>'
                    .htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
    }

    private function columnLetter(int $index): string
    {
        $letters = '';

        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $letters = chr(65 + (($i - 1) % 26)).$letters;
        }

        return $letters;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
