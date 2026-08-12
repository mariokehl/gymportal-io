<?php

namespace Tests\Feature\Services;

use App\Models\CollectionCase;
use App\Models\CollectionClaim;
use App\Models\Gym;
use App\Models\Member;
use App\Services\Diagonal\DiagonalApiException;
use App\Services\Diagonal\DiagonalCaseMapper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagonalCaseMapperTest extends TestCase
{
    use RefreshDatabase;

    private DiagonalCaseMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = app(DiagonalCaseMapper::class);
    }

    private function gym(array $settings = []): Gym
    {
        $gym = Gym::factory()->create();
        $gym->update([
            'inkasso_settings' => array_merge($gym->inkasso_settings, array_merge([
                'active' => true,
                'tenant_id' => '40218-BER',
                'client_number' => '40218',
            ], $settings)),
        ]);

        return $gym->fresh();
    }

    private function member(Gym $gym, array $attributes = []): Member
    {
        return Member::factory()->create(array_merge([
            'gym_id' => $gym->id,
            'salutation' => 'Frau',
            'first_name' => 'Susi',
            'last_name' => 'Summs',
            'address' => 'Musterstraße 12a',
            'postal_code' => '10115',
            'city' => 'Berlin',
            'country' => 'Deutschland',
            'birth_date' => '1990-05-17',
        ], $attributes));
    }

    /**
     * @param  array<int, array{amount: float, kind: string}>  $claims
     */
    private function case(Gym $gym, Member $member, array $claims): CollectionCase
    {
        $case = CollectionCase::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'case_number' => CollectionCase::generateCaseNumber($gym->id),
            'status' => CollectionCase::STATUS_IN_PROGRESS,
            'handed_over_at' => Carbon::parse('2026-08-01'),
        ]);

        foreach ($claims as $claim) {
            CollectionClaim::create([
                'gym_id' => $gym->id,
                'collection_case_id' => $case->id,
                'description' => $claim['description'] ?? 'Mitgliedsbeitrag',
                'due_date' => $claim['due_date'] ?? '2026-07-11',
                'amount' => $claim['amount'],
                'kind' => $claim['kind'],
            ]);
        }

        return $case->fresh();
    }

    public function test_it_maps_claim_kinds_to_the_matching_lists(): void
    {
        $gym = $this->gym();
        $member = $this->member($gym);
        $case = $this->case($gym, $member, [
            ['amount' => 49.99, 'kind' => CollectionClaim::KIND_PRINCIPAL],
            ['amount' => 49.99, 'kind' => CollectionClaim::KIND_PRINCIPAL],
            ['amount' => 10.0, 'kind' => CollectionClaim::KIND_DUNNING],
            ['amount' => 58.5, 'kind' => CollectionClaim::KIND_FLAT],
        ]);

        $payload = $this->mapper->toFileDataItem($case, $gym);

        $this->assertCount(2, $payload['invoiceList']);
        $this->assertCount(1, $payload['dunningList']);
        $this->assertCount(1, $payload['expensesList']);
        $this->assertEquals(10.0, $payload['dunningList'][0]['dunningSum']);
        $this->assertEquals(58.5, $payload['expensesList'][0]['expensesSum']);
        $this->assertSame('2026-08-01', $payload['effectDate']);
    }

    public function test_creditor_number_must_be_five_characters(): void
    {
        $gym = $this->gym(['client_number' => '123']);
        $case = $this->case($gym, $this->member($gym), [
            ['amount' => 49.99, 'kind' => CollectionClaim::KIND_PRINCIPAL],
        ]);

        $this->expectException(DiagonalApiException::class);
        $this->expectExceptionMessage('genau 5 Zeichen');

        $this->mapper->toFileDataItem($case, $gym);
    }

    public function test_it_maps_the_salutation_to_the_gender_enum(): void
    {
        $gym = $this->gym();

        $female = $this->mapper->debtor($this->member($gym, ['salutation' => 'Frau']));
        $male = $this->mapper->debtor($this->member($gym, ['salutation' => 'Herr']));
        $unknown = $this->mapper->debtor($this->member($gym, ['salutation' => null]));

        $this->assertSame('female', $female['gender']);
        $this->assertSame('male', $male['gender']);
        $this->assertSame('unknown', $unknown['gender']);
        $this->assertSame(DiagonalCaseMapper::DEBTOR_TYPE_CONSUMER, $female['debtorType']);
    }

    public function test_it_splits_street_and_house_number(): void
    {
        $gym = $this->gym();

        $address = $this->mapper->address($this->member($gym, ['address' => 'Musterstraße 12a']));

        $this->assertSame('Musterstraße', $address['street']);
        $this->assertSame('12a', $address['streetNumber']);
        $this->assertSame('10115', $address['postalCode']);
        $this->assertSame('Berlin', $address['city']);
        $this->assertSame('DE', $address['countryCode']);
    }

    public function test_it_maps_the_country_to_an_iso_code(): void
    {
        $gym = $this->gym();

        $this->assertSame('AT', $this->mapper->address($this->member($gym, ['country' => 'Österreich']))['countryCode']);
        $this->assertSame('CH', $this->mapper->address($this->member($gym, ['country' => 'CH']))['countryCode']);
        $this->assertSame('DE', $this->mapper->address($this->member($gym, ['country' => null]))['countryCode']);
    }

    public function test_it_truncates_names_to_the_allowed_length(): void
    {
        $gym = $this->gym();
        $member = $this->member($gym, ['last_name' => str_repeat('A', 60)]);

        $debtor = $this->mapper->debtor($member);

        $this->assertSame(40, mb_strlen($debtor['lastName']));
    }

    public function test_incomplete_address_data_is_rejected_before_sending(): void
    {
        $gym = $this->gym();
        $member = $this->member($gym, ['address' => '', 'city' => '', 'postal_code' => '']);
        $case = $this->case($gym, $member, [
            ['amount' => 49.99, 'kind' => CollectionClaim::KIND_PRINCIPAL],
        ]);

        $this->expectException(DiagonalApiException::class);
        $this->expectExceptionMessage('Unvollständige Mitgliedsdaten');

        $this->mapper->toFileDataItem($case, $gym);
    }

    public function test_synthetic_import_emails_are_never_transmitted(): void
    {
        $gym = $this->gym();
        $member = $this->member($gym, ['email' => 'someone'.Member::SYNTHETIC_EMAIL_DOMAIN]);

        $debtor = $this->mapper->debtor($member);

        $this->assertArrayNotHasKey('email', $debtor['contactDetails'] ?? []);
    }

    public function test_claims_below_the_minimum_invoice_amount_are_skipped(): void
    {
        $gym = $this->gym();
        $member = $this->member($gym);
        $case = $this->case($gym, $member, [
            ['amount' => 49.99, 'kind' => CollectionClaim::KIND_PRINCIPAL],
            ['amount' => 0.20, 'kind' => CollectionClaim::KIND_PRINCIPAL],
        ]);

        $payload = $this->mapper->toFileDataItem($case, $gym);

        // The 0.20 € claim is below the API minimum of 0.50 €.
        $this->assertCount(1, $payload['invoiceList']);
    }

    public function test_a_case_without_transmittable_principal_is_rejected(): void
    {
        $gym = $this->gym();
        $member = $this->member($gym);
        $case = $this->case($gym, $member, [
            ['amount' => 58.5, 'kind' => CollectionClaim::KIND_FLAT],
        ]);

        $this->expectException(DiagonalApiException::class);
        $this->expectExceptionMessage('keine übertragbare Hauptforderung');

        $this->mapper->toFileDataItem($case, $gym);
    }

    public function test_payment_and_cancellation_items_require_a_transmitted_case(): void
    {
        $gym = $this->gym();
        $case = $this->case($gym, $this->member($gym), [
            ['amount' => 49.99, 'kind' => CollectionClaim::KIND_PRINCIPAL],
        ]);

        $this->expectException(DiagonalApiException::class);
        $this->expectExceptionMessage('noch nicht an DIAGONAL übertragen');

        $this->mapper->toCancellationItem($case);
    }

    public function test_cancellation_item_carries_guid_reason_and_effect_date(): void
    {
        $gym = $this->gym();
        $case = $this->case($gym, $this->member($gym), [
            ['amount' => 49.99, 'kind' => CollectionClaim::KIND_PRINCIPAL],
        ]);
        $case->update(['diagonal_guid' => 'abc-123']);

        $item = $this->mapper->toCancellationItem($case->fresh(), 'Retoure', 'Storniert durch das Studio');

        $this->assertSame('abc-123', $item['guid']);
        $this->assertSame('Retoure', $item['cancellationReason']);
        $this->assertSame(Carbon::today()->toDateString(), $item['effectDate']);
        $this->assertSame('Storniert durch das Studio', $item['information']);
    }
}
