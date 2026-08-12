<?php

namespace App\Services\Diagonal;

use App\Models\CollectionCase;
use App\Models\CollectionClaim;
use App\Models\CollectionPayment;
use App\Models\Gym;
use App\Models\Member;
use Carbon\Carbon;

/**
 * Builds DIAGONAL API payloads from our own collection models.
 *
 * The field limits mirror the OpenAPI v1.2 schema so that malformed cases are
 * rejected here instead of being refused by the partner later on.
 */
class DiagonalCaseMapper
{
    /** DebtorDataItem.debtorType */
    public const DEBTOR_TYPE_COMPANY = 0;

    public const DEBTOR_TYPE_CONSUMER = 1;

    /** The smallest amount InvoiceFileDataItem.totalBrutto accepts. */
    public const MIN_INVOICE_AMOUNT = 0.5;

    /**
     * Build the FileDataItem payload for a case.
     *
     * @return array<string, mixed>
     *
     * @throws DiagonalApiException when the case cannot be represented
     */
    public function toFileDataItem(CollectionCase $case, ?Gym $gym = null): array
    {
        $gym ??= $case->gym;
        $member = $case->member;

        if (! $member) {
            throw new DiagonalApiException('Zur Akte ist kein Mitglied hinterlegt.');
        }

        $this->assertMemberIsTransmittable($member);

        $claims = $case->relationLoaded('claims') ? $case->claims : $case->claims()->get();

        $payload = [
            'creditor' => $this->creditor($gym),
            'debtor' => $this->debtor($member, $case),
            'invoiceList' => $this->invoiceList($claims, $case),
        ];

        $dunning = $this->dunningList($claims);
        if ($dunning !== []) {
            $payload['dunningList'] = $dunning;
        }

        $expenses = $this->expensesList($claims);
        if ($expenses !== []) {
            $payload['expensesList'] = $expenses;
        }

        if ($case->handed_over_at) {
            $payload['effectDate'] = $case->handed_over_at->toDateString();
        }

        return $payload;
    }

    /**
     * ClientDataItem: the creditor number has to be exactly five characters.
     *
     * @return array<string, string>
     */
    public function creditor(Gym $gym): array
    {
        $clientNumber = (string) ($gym->inkasso_settings['client_number'] ?? '');

        if (strlen($clientNumber) !== 5) {
            throw new DiagonalApiException(
                'Die DIAGONAL-Gläubigernummer muss genau 5 Zeichen lang sein.'
            );
        }

        return ['clientNumber' => $clientNumber];
    }

    /**
     * @return array<string, mixed>
     */
    public function debtor(Member $member, ?CollectionCase $case = null): array
    {
        $debtor = [
            'gender' => $this->gender($member),
            'debtorType' => self::DEBTOR_TYPE_CONSUMER,
            'firstName' => $this->truncate($member->first_name, 40),
            'lastName' => $this->truncate($member->last_name, 40),
            'address' => $this->address($member),
        ];

        if ($member->birth_date) {
            $debtor['dateOfBirth'] = Carbon::parse($member->birth_date)->toDateString();
        }

        if ($case) {
            $debtor['debtorReferenceNumber'] = $this->truncate($case->case_number, 36);
        }

        $contact = $this->contactDetails($member);
        if ($contact !== []) {
            $debtor['contactDetails'] = $contact;
        }

        return $debtor;
    }

    /**
     * AddressDataItem. Street and house number are stored in one field in our
     * data model, so they are split heuristically.
     *
     * @return array<string, string>
     */
    public function address(Member $member): array
    {
        [$street, $streetNumber] = $this->splitStreet((string) $member->address);

        $address = [
            'street' => $this->truncate($street, 40),
            'postalCode' => $this->truncate((string) $member->postal_code, 10),
            'city' => $this->truncate((string) $member->city, 30),
            'countryCode' => $this->countryCode($member),
        ];

        if ($streetNumber !== '') {
            $address['streetNumber'] = $this->truncate($streetNumber, 10);
        }

        return $address;
    }

    /**
     * @return array<string, string>
     */
    public function contactDetails(Member $member): array
    {
        $details = [];

        // Imported members carry a synthetic address that must never be used.
        if ($member->email && ! Member::isSyntheticEmail($member->email)) {
            $details['email'] = $member->email;
        }

        if ($member->phone) {
            $details['phone'] = $member->phone;
        }

        return $details;
    }

    /**
     * Principal claims become invoiceList entries.
     *
     * @param  iterable<int, CollectionClaim>  $claims
     * @return array<int, array<string, mixed>>
     */
    public function invoiceList(iterable $claims, CollectionCase $case): array
    {
        $invoices = [];
        $index = 0;

        foreach ($claims as $claim) {
            if ($claim->kind !== CollectionClaim::KIND_PRINCIPAL) {
                continue;
            }

            $amount = round((float) $claim->amount, 2);

            if ($amount < self::MIN_INVOICE_AMOUNT) {
                continue;
            }

            $index++;

            $invoices[] = [
                'invoiceNumber' => $case->case_number.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'invoiceDate' => $claim->due_date
                    ? Carbon::parse($claim->due_date)->toDateString()
                    : Carbon::today()->toDateString(),
                'totalBrutto' => $amount,
                'information' => $this->truncate((string) $claim->description, 255),
            ];
        }

        if ($invoices === []) {
            throw new DiagonalApiException(
                'Die Akte enthält keine übertragbare Hauptforderung (Mindestbetrag 0,50 €).'
            );
        }

        return $invoices;
    }

    /**
     * Dunning fees are summarised into dunningList entries.
     *
     * @param  iterable<int, CollectionClaim>  $claims
     * @return array<int, array<string, mixed>>
     */
    public function dunningList(iterable $claims): array
    {
        $entries = [];

        foreach ($claims as $claim) {
            if ($claim->kind !== CollectionClaim::KIND_DUNNING) {
                continue;
            }

            $entries[] = [
                'dunningDate' => $claim->due_date
                    ? Carbon::parse($claim->due_date)->toDateString()
                    : Carbon::today()->toDateString(),
                'dunningSum' => round((float) $claim->amount, 2),
            ];
        }

        return $entries;
    }

    /**
     * Handover flat fees become expensesList entries.
     *
     * @param  iterable<int, CollectionClaim>  $claims
     * @return array<int, array<string, mixed>>
     */
    public function expensesList(iterable $claims): array
    {
        $entries = [];

        foreach ($claims as $claim) {
            if ($claim->kind !== CollectionClaim::KIND_FLAT) {
                continue;
            }

            $entries[] = [
                'expensesDate' => $claim->due_date
                    ? Carbon::parse($claim->due_date)->toDateString()
                    : Carbon::today()->toDateString(),
                'expensesSum' => round((float) $claim->amount, 2),
            ];
        }

        return $entries;
    }

    /**
     * PaymentDataItem for a payment booked on an already transmitted case.
     *
     * @return array<string, mixed>
     */
    public function toPaymentDataItem(CollectionPayment $payment, CollectionCase $case): array
    {
        if (! $case->diagonal_guid) {
            throw new DiagonalApiException('Die Akte wurde noch nicht an DIAGONAL übertragen.');
        }

        return [
            'guid' => $case->diagonal_guid,
            'paymentDate' => Carbon::parse($payment->booked_at)->toDateString(),
            'paymentAmount' => round((float) $payment->amount, 2),
            'paymentType' => 'Payment',
        ];
    }

    /**
     * FileCancellationItem for cancelling a transmitted case.
     *
     * @return array<string, mixed>
     */
    public function toCancellationItem(CollectionCase $case, string $reason = 'Goodwill', ?string $information = null): array
    {
        if (! $case->diagonal_guid) {
            throw new DiagonalApiException('Die Akte wurde noch nicht an DIAGONAL übertragen.');
        }

        return array_filter([
            'guid' => $case->diagonal_guid,
            'cancellationReason' => $reason,
            'effectDate' => Carbon::today()->toDateString(),
            'information' => $information,
        ], fn ($value) => $value !== null);
    }

    /**
     * The partner requires a postal address; without it the case is rejected.
     *
     * @throws DiagonalApiException
     */
    public function assertMemberIsTransmittable(Member $member): void
    {
        $missing = [];

        if (strlen(trim((string) $member->last_name)) < 2) {
            $missing[] = 'Nachname';
        }

        if (trim((string) $member->address) === '') {
            $missing[] = 'Straße';
        }

        if (strlen(trim((string) $member->postal_code)) < 3) {
            $missing[] = 'Postleitzahl';
        }

        if (strlen(trim((string) $member->city)) < 3) {
            $missing[] = 'Ort';
        }

        if ($missing !== []) {
            throw new DiagonalApiException(
                'Unvollständige Mitgliedsdaten für die Inkassoübergabe: '.implode(', ', $missing).'.'
            );
        }
    }

    protected function gender(Member $member): string
    {
        return match (mb_strtolower(trim((string) $member->salutation))) {
            'herr' => 'male',
            'frau' => 'female',
            'divers', 'diverse' => 'diverse',
            default => 'unknown',
        };
    }

    protected function countryCode(Member $member): string
    {
        $country = trim((string) $member->country);

        if ($country === '') {
            return 'DE';
        }

        // Already an ISO code.
        if (strlen($country) <= 3) {
            return strtoupper($country);
        }

        return match (mb_strtolower($country)) {
            'deutschland', 'germany' => 'DE',
            'österreich', 'oesterreich', 'osterreich', 'austria' => 'AT',
            'schweiz', 'switzerland', 'suisse' => 'CH',
            default => 'DE',
        };
    }

    /**
     * Split "Musterstraße 12a" into street and house number.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitStreet(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            return ['', ''];
        }

        if (preg_match('/^(.*?)\s+(\d+\s*[a-zA-Z]?(?:\s*[-\/]\s*\d+\s*[a-zA-Z]?)?)$/u', $address, $matches)) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return [$address, ''];
    }

    protected function truncate(?string $value, int $length): string
    {
        return mb_substr(trim((string) $value), 0, $length);
    }
}
