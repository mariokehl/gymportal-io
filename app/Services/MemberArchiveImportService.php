<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberAccessConfig;
use App\Models\MemberCreditLedger;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Imports member archives exported from a third-party gym management system.
 *
 * The archive keeps the running contracts of every member, so the goal is a
 * seamless handover: the tariffs, the SEPA mandate and any credit balance are
 * taken over unchanged and the next charge is created for the day after the
 * period the previous system already collected ("Bezahlt bis").
 *
 * A member can hold several contracts at once, each of them with its own
 * modules. Every contract becomes a membership of its own, and the member
 * stays active as long as at least one of them is still running.
 */
class MemberArchiveImportService
{
    public function __construct(
        private MemberArchiveParser $parser,
        private PaymentService $paymentService,
        private CreditLedgerService $creditLedgerService,
        private MollieService $mollieService,
    ) {}

    /**
     * Analyse an archive without writing anything.
     *
     * @param  array<int, string>  $folders
     */
    public function analyse(int $gymId, array $folders): array
    {
        $plans = MembershipPlan::where('gym_id', $gymId)->get();
        $addons = Addon::where('gym_id', $gymId)->get();

        $members = [];
        $errors = [];
        $warnings = [];

        $stats = [
            'folders' => count($folders),
            'members' => 0,
            'existing_members' => 0,
            'plans_matched' => 0,
            'plans_new' => 0,
            'modules' => 0,
            'modules_matched' => 0,
            'sepa_mandates' => 0,
            'credit_balances' => 0,
            'access_tags' => 0,
            'legal_guardians' => 0,
        ];

        $newPlanNames = [];
        $newAddonNames = [];

        foreach ($folders as $folder) {
            try {
                $data = $this->parser->parseMemberFolder($folder);
            } catch (\Throwable $e) {
                $errors[] = basename($folder).': '.$e->getMessage();

                continue;
            }

            $label = trim($data['member']['first_name'].' '.$data['member']['last_name']);
            $stats['members']++;

            if ($data['member']['email'] === '') {
                $warnings[] = "{$label}: Keine E-Mail-Adresse hinterlegt, es wird eine Platzhalter-Adresse vergeben.";
            }

            if ($this->findExistingMember($gymId, $data) !== null) {
                $stats['existing_members']++;
                $warnings[] = "{$label}: Mitglied existiert bereits und wird übersprungen.";
            }

            $planNames = [];
            $moduleNames = [];
            $price = 0.0;
            $allPlansMatched = true;

            foreach ($data['contracts'] as $entry) {
                $planName = $entry['contract']['plan_name'];
                $planNames[] = $planName;
                $price += (float) ($entry['contract']['price'] ?? 0);

                if ($this->matchPlan($plans, $planName, $entry['contract']['price'])) {
                    $stats['plans_matched']++;
                } else {
                    $stats['plans_new']++;
                    $newPlanNames[$planName] = true;
                    $allPlansMatched = false;
                }

                foreach ($entry['modules'] as $module) {
                    $stats['modules']++;
                    $moduleNames[] = $module['name'];

                    if ($this->matchAddon($addons, $module['name'])) {
                        $stats['modules_matched']++;
                    } else {
                        $newAddonNames[$module['name']] = true;
                    }
                }
            }

            if ($data['bank_account'] && $data['bank_account']['mandate_reference']) {
                $stats['sepa_mandates']++;
            } elseif (! $data['bank_account']) {
                $warnings[] = "{$label}: Keine Bankverbindung im Export, das SEPA-Mandat kann nicht übernommen werden.";
            }

            if ($this->totalCredit($data) > 0) {
                $stats['credit_balances']++;
            }

            if ($data['access_tags']['nfc_uid']) {
                $stats['access_tags']++;
            }

            if ($data['legal_guardian']) {
                $stats['legal_guardians']++;
            }

            // The member stays active as long as a single contract is still
            // running; only when every one of them has ended is the membership
            // treated as finished.
            $running = array_values(array_filter(
                $data['contracts'],
                fn ($entry) => ! $this->contractHasEnded($entry['contract'])
            ));

            $hasEnded = $running === [];

            if ($hasEnded) {
                $lastEnd = $this->latestContractEnd($data['contracts']);
                $warnings[] = "{$label}: Die Mitgliedschaft ist am ".Carbon::parse($lastEnd)->format('d.m.Y').' beendet, es wird keine Abrechnung angelegt.';
            } elseif ($this->earliestPaidUntil($running, $data) === null) {
                $warnings[] = "{$label}: Kein \"Bezahlt bis\"-Datum vorhanden, die Abrechnung startet am gewählten Stichtag.";
            }

            $nextCharge = null;

            foreach ($running as $entry) {
                $date = $this->nextChargeDate(
                    $entry['contract']['paid_until'] ?? $data['paid_until'],
                    null,
                    $this->contractEnd($entry['contract'])
                );

                if ($date && (! $nextCharge || $date->lt($nextCharge))) {
                    $nextCharge = $date;
                }
            }

            $members[] = [
                'folder' => $data['source_folder'],
                'name' => $label,
                'member_number' => $data['member']['member_number'],
                'plan_name' => implode(', ', array_filter($planNames)),
                'plan_matched' => $allPlansMatched,
                'price' => round($price, 2),
                'modules' => $moduleNames,
                'has_sepa' => $data['bank_account'] !== null,
                'credit' => $this->totalCredit($data),
                'paid_until' => $data['paid_until'],
                'membership_ended' => $hasEnded,
                'next_charge' => $nextCharge?->toDateString(),
            ];
        }

        return [
            'valid' => $stats['members'] > 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'stats' => $stats,
            'new_plans' => array_keys($newPlanNames),
            'new_addons' => array_keys($newAddonNames),
            'members' => $members,
        ];
    }

    /**
     * Import all member folders of an archive.
     *
     * @param  array<int, string>  $folders
     * @param  string|null  $fallbackStartDate  used when a record has no "Bezahlt bis" date
     */
    public function import(int $gymId, array $folders, ?string $fallbackStartDate = null, bool $createMissingPlans = true): array
    {
        $gym = Gym::findOrFail($gymId);
        $fallback = $fallbackStartDate ? Carbon::parse($fallbackStartDate) : null;

        $stats = [
            'members_created' => 0,
            'memberships_created' => 0,
            'plans_created' => 0,
            'addons_created' => 0,
            'addons_booked' => 0,
            'payment_methods_created' => 0,
            'payments_created' => 0,
            'credit_entries_created' => 0,
            'access_configs_created' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($folders as $folder) {
            try {
                DB::transaction(function () use ($gym, $folder, $fallback, $createMissingPlans, &$stats) {
                    $this->importMemberFolder($gym, $folder, $fallback, $createMissingPlans, $stats);
                });
            } catch (\Throwable $e) {
                $stats['skipped']++;
                $stats['errors'][] = basename($folder).': '.$e->getMessage();

                Log::error('Member archive import failed', [
                    'gym_id' => $gymId,
                    'folder' => basename($folder),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Import one member folder. Runs inside its own transaction so a single
     * broken record cannot roll back the whole archive.
     */
    private function importMemberFolder(Gym $gym, string $folder, ?Carbon $fallback, bool $createMissingPlans, array &$stats): void
    {
        $data = $this->parser->parseMemberFolder($folder);

        if ($this->findExistingMember($gym->id, $data) !== null) {
            $stats['skipped']++;

            return;
        }

        $member = $this->createMember($gym, $data);
        $stats['members_created']++;

        $paymentMethod = $this->createPaymentMethod($member, $data);

        if ($paymentMethod) {
            $stats['payment_methods_created']++;
        }

        $this->importAccessConfig($member, $data);

        if ($data['access_tags']['nfc_uid']) {
            $stats['access_configs_created']++;
        }

        $this->importCredit($member, $data, $stats);

        // Every contract of the export becomes its own membership, together
        // with the modules booked on top of it.
        foreach ($data['contracts'] as $entry) {
            $this->importContract($gym, $member, $data, $entry, $fallback, $createMissingPlans, $stats);
        }

        $this->syncMemberStatus($member);
    }

    /**
     * Import one contract of a member as a membership including its modules
     * and the follow-up charge.
     *
     * @param  array{contract: array<string, mixed>, modules: array<int, array<string, mixed>>}  $entry
     */
    private function importContract(Gym $gym, Member $member, array $data, array $entry, ?Carbon $fallback, bool $createMissingPlans, array &$stats): void
    {
        $contract = $entry['contract'];
        $plan = $this->resolvePlan($gym, $contract, $createMissingPlans, $stats);

        if (! $plan) {
            throw new \RuntimeException("Kein Tarif für \"{$contract['plan_name']}\" gefunden.");
        }

        $membership = $this->createMembership($member, $plan, $data, $contract);
        $stats['memberships_created']++;

        $this->bookModules($gym, $membership, $entry['modules'], $createMissingPlans, $stats);

        // The previous system has already collected up to "Bezahlt bis", so the
        // charge in gymportal.io continues the day after that period. An ended
        // membership is only archived and must never be charged again.
        $anchor = $membership->status === 'active'
            ? $this->nextChargeDate($contract['paid_until'] ?? $data['paid_until'], $fallback, $membership->end_date)
            : null;

        if (! $anchor) {
            return;
        }

        // A free membership has nothing to collect, so no charge is scheduled
        // for it. The membership itself is still imported and stays active.
        if ((float) $plan->price <= 0) {
            return;
        }

        // The member has been paying all along, so this continues the
        // running series instead of starting a new one: it keeps the
        // recurring execution offset and a period-based description
        // rather than announcing a "1. Mitgliedsbeitrag".
        // createRecurringPayments() yields nothing once the anchor sits
        // past the contract end, so take the array form rather than
        // createNextRecurringPayment(), which would index into an empty
        // result.
        $payments = $this->paymentService->createRecurringPayments(
            $member->fresh(),
            $membership,
            1,
            $anchor
        );

        if ($payments !== []) {
            $stats['payments_created']++;
        }
    }

    /**
     * A member is active as soon as one of their memberships is still running.
     * Only when every contract has ended is the member archived as inactive,
     * following the same rule Membership::markAsExpired() applies. The payment
     * details are retired along with it so no mandate stays collectable.
     */
    private function syncMemberStatus(Member $member): void
    {
        if ($member->memberships()->where('status', 'active')->exists()) {
            return;
        }

        $member->update(['status' => 'inactive']);

        // "expired" is the enum value the payment method uses to mark itself
        // inactive; there is no separate "inactive" state.
        $member->paymentMethods()->update([
            'status' => 'expired',
            'sepa_mandate_status' => 'expired',
        ]);
    }

    /**
     * Create the member record including the legal guardian for minors.
     */
    private function createMember(Gym $gym, array $data): Member
    {
        $source = $data['member'];
        $email = $source['email'];

        // Keep the email unique per gym; fall back to a placeholder otherwise.
        if ($email === '' || Member::where('gym_id', $gym->id)->where('email', $email)->exists()) {
            $email = MemberService::generatePlaceholderEmail();
        }

        $attributes = [
            'gym_id' => $gym->id,
            'member_number' => MemberService::generateMemberNumber($gym),
            'salutation' => $source['salutation'],
            'first_name' => $source['first_name'],
            'last_name' => $source['last_name'],
            'email' => $email,
            'phone' => $source['phone'],
            'birth_date' => $source['birth_date'],
            'address' => $source['address'],
            'address_addition' => $source['address_addition'],
            'postal_code' => $source['postal_code'],
            'city' => $source['city'],
            'country' => $source['country'] ?: 'DE',
            'status' => 'active',
            'joined_date' => $this->earliestContractStart($data) ?? now()->toDateString(),
            'registration_source' => 'archive_import',
            'notes' => $this->buildImportNote($data, $source['notes']),
        ];

        if ($data['legal_guardian']) {
            $attributes['legal_guardian_first_name'] = $data['legal_guardian']['first_name'];
            $attributes['legal_guardian_last_name'] = $data['legal_guardian']['last_name'];
        }

        return Member::create($attributes);
    }

    /**
     * Keep the original member number traceable after the migration.
     */
    private function buildImportNote(array $data, ?string $existingNotes): ?string
    {
        $lines = array_filter([
            $existingNotes,
            'Übernommen aus Voranbieter-Export, ursprüngliche Mitgliedsnummer: '.$data['member']['member_number'],
        ]);

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * Find a matching plan or create one from the contract terms.
     */
    private function resolvePlan(Gym $gym, array $contract, bool $createMissing, array &$stats): ?MembershipPlan
    {
        $plans = MembershipPlan::where('gym_id', $gym->id)->get();
        $plan = $this->matchPlan($plans, $contract['plan_name'], $contract['price']);

        if ($plan) {
            return $plan;
        }

        if (! $createMissing || $contract['plan_name'] === '') {
            return null;
        }

        // A contract the export lists without a price is a free membership and
        // is carried over under its own name at 0 €.
        $price = $contract['price'] ?? 0.0;

        $cancellation = $contract['cancellation_period'] ?? ['value' => 30, 'unit' => 'days'];

        $plan = MembershipPlan::create([
            'gym_id' => $gym->id,
            'name' => $contract['plan_name'],
            'description' => 'Aus einem Voranbieter-Export übernommen.',
            'price' => $price,
            'setup_fee' => $contract['setup_fee'] ?? 0,
            'billing_cycle' => $contract['billing_cycle'],
            'commitment_months' => $contract['commitment_months'],
            'cancellation_period' => $cancellation['value'],
            'cancellation_period_unit' => $cancellation['unit'],
            // A one-month extension renews monthly, any longer extension term
            // is treated as an open-ended renewal.
            'auto_renew_type' => $contract['renewal_months'] > 1 ? 'indefinite' : 'monthly',
            'is_active' => true,
        ]);

        $stats['plans_created']++;

        return $plan;
    }

    /**
     * Create the membership, carrying over an existing cancellation.
     */
    private function createMembership(Member $member, MembershipPlan $plan, array $data, array $contract): Membership
    {
        $startDate = $contract['start_date'] ? Carbon::parse($contract['start_date']) : now();

        $membership = Membership::create([
            'member_id' => $member->id,
            'membership_plan_id' => $plan->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $contract['end_date'],
            'status' => $this->membershipStatus($contract['end_date']),
            'metadata' => [
                'imported_from_archive' => true,
                'source_member_number' => $data['member']['member_number'],
                'source_plan_name' => $contract['plan_name'],
                'source_paid_until' => $contract['paid_until'] ?? $data['paid_until'],
                'source_price' => $contract['price'],
            ],
        ]);

        // A contract that was already cancelled keeps its end date and reason.
        if ($contract['cancelled_to']) {
            $membership->update([
                'status' => $this->membershipStatus($contract['cancelled_to']),
                'end_date' => $contract['cancelled_to'],
                'cancellation_date' => $contract['cancelled_at'],
                'cancellation_reason' => $contract['cancellation_reason'],
            ]);
        }

        return $membership;
    }

    /**
     * The day a contract actually stops: a cancellation takes precedence over
     * the agreed end date.
     */
    private function contractEnd(array $contract): ?string
    {
        return $contract['cancelled_to'] ?: $contract['end_date'];
    }

    /**
     * A contract counts as ended once its last day has passed. A member is
     * only inactive when this holds for every one of their contracts.
     */
    private function contractHasEnded(array $contract): bool
    {
        return $this->membershipStatus($this->contractEnd($contract)) === 'expired';
    }

    /**
     * The last day on which any contract of the member was still running.
     *
     * @param  array<int, array{contract: array<string, mixed>}>  $contracts
     */
    private function latestContractEnd(array $contracts): ?string
    {
        $latest = null;

        foreach ($contracts as $entry) {
            $end = $this->contractEnd($entry['contract']);

            if ($end && (! $latest || Carbon::parse($end)->gt(Carbon::parse($latest)))) {
                $latest = $end;
            }
        }

        return $latest;
    }

    /**
     * The first day the member joined, i.e. the start of their oldest contract.
     */
    private function earliestContractStart(array $data): ?string
    {
        $earliest = null;

        foreach ($data['contracts'] as $entry) {
            $start = $entry['contract']['start_date'] ?? null;

            if ($start && (! $earliest || Carbon::parse($start)->lt(Carbon::parse($earliest)))) {
                $earliest = $start;
            }
        }

        return $earliest;
    }

    /**
     * The earliest "Bezahlt bis" among the given contracts, or null when not a
     * single one of them carries the date.
     *
     * @param  array<int, array{contract: array<string, mixed>}>  $contracts
     */
    private function earliestPaidUntil(array $contracts, array $data): ?string
    {
        $earliest = null;

        foreach ($contracts as $entry) {
            $paidUntil = $entry['contract']['paid_until'] ?? $data['paid_until'];

            if ($paidUntil && (! $earliest || Carbon::parse($paidUntil)->lt(Carbon::parse($earliest)))) {
                $earliest = $paidUntil;
            }
        }

        return $earliest;
    }

    /**
     * A contract whose last day has already passed is imported as expired,
     * following the same rule the expiry job applies (end_date < today).
     */
    private function membershipStatus(?string $endDate): string
    {
        if ($endDate && Carbon::parse($endDate)->lt(Carbon::today())) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Take over the SEPA mandate so the member does not have to sign again.
     */
    private function createPaymentMethod(Member $member, array $data): ?PaymentMethod
    {
        $bank = $data['bank_account'];

        if (! $bank) {
            return null;
        }

        // A studio that collects its direct debits through an integration has
        // the standard method overridden, so the mandate has to be taken over
        // into that integration rather than kept in-house.
        $type = $member->gym->resolvePaymentMethodKey('sepa_direct_debit');

        $paymentMethod = PaymentMethod::create([
            'member_id' => $member->id,
            'type' => $type,
            'status' => 'active',
            'is_default' => true,
            'requires_mandate' => true,
            'iban' => $bank['iban'],
            'bank_name' => $bank['bank_name'],
            'account_holder' => $bank['account_holder'],
            // The mandate was granted to the studio before the migration and
            // stays valid, so it is imported as signed rather than pending.
            'sepa_mandate_acknowledged' => true,
            'sepa_mandate_status' => 'active',
            'sepa_mandate_reference' => $bank['mandate_reference'],
            'sepa_mandate_signed_at' => $bank['mandate_signed_at'],
            'sepa_mandate_data' => [
                'imported_from_archive' => true,
                'original_reference' => $bank['mandate_reference'],
                'granted_at' => $bank['mandate_signed_at'],
                'bic' => $bank['bic'],
            ],
        ]);

        $this->registerMandateWithIntegration($member, $paymentMethod);

        return $paymentMethod;
    }

    /**
     * Hand the mandate over to the payment integration, the same way a
     * contract signed through the online widget does. A failure here must not
     * roll back the member, so the mandate stays on record for a retry.
     */
    private function registerMandateWithIntegration(Member $member, PaymentMethod $paymentMethod): void
    {
        if (! str_starts_with($paymentMethod->type, 'mollie_')) {
            return;
        }

        try {
            $this->mollieService->handleMolliePaymentMethod($member, $paymentMethod);
        } catch (\Throwable $e) {
            Log::error('Mollie mandate creation failed during archive import', [
                'member_id' => $member->id,
                'payment_method_id' => $paymentMethod->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Book module contracts (e.g. a drinks flat rate) as recurring add-ons.
     */
    private function bookModules(Gym $gym, Membership $membership, array $modules, bool $createMissing, array &$stats): void
    {
        if ($modules === []) {
            return;
        }

        $addons = Addon::where('gym_id', $gym->id)->get();

        foreach ($modules as $module) {
            $addon = $this->matchAddon($addons, $module['name']);

            if (! $addon && $createMissing) {
                $addon = $this->createAddonForModule($gym, $module);
                $addons->push($addon);
                $stats['addons_created']++;
            }

            if (! $addon) {
                continue;
            }

            // The module has been running since the previous system booked it,
            // so its own start date is carried over instead of leaving the
            // import day to stand in for it.
            $membership->addons()->attach($addon->id, [
                'mode' => 'optional',
                'price' => $module['price'] ?? $addon->price,
                'booked_at' => $module['start_date'] ?? $membership->start_date->toDateString(),
            ]);

            $stats['addons_booked']++;
        }
    }

    /**
     * Create an add-on matching a module contract from the export.
     */
    private function createAddonForModule(Gym $gym, array $module): Addon
    {
        // A drinks or similar consumption module is a flat rate settled at the
        // dispenser, which maps onto a usage service without a quota limit.
        $isConsumption = (bool) preg_match('/getränk|drink|kaffee|coffee|shake/i', $module['name']);

        return Addon::create([
            'gym_id' => $gym->id,
            'name' => $module['name'],
            'description' => 'Aus einem Voranbieter-Export übernommen.',
            'service_type' => $isConsumption ? 'usage' : 'additional',
            'billing_type' => 'recurring',
            'price' => $module['price'] ?? 0,
            'quota_amount' => null,
            'quota_interval' => $isConsumption ? 'month' : null,
            'settled_via_device' => $isConsumption,
            'usage_period' => $isConsumption ? 'single' : null,
            'is_active' => true,
        ]);
    }

    /**
     * Carry over the account balance and the consumption credit.
     */
    private function importCredit(Member $member, array $data, array &$stats): void
    {
        $amount = $this->totalCredit($data);

        if ($amount <= 0) {
            return;
        }

        $cents = $this->creditLedgerService->toCents($amount);

        $this->creditLedgerService->credit(
            $member,
            $cents,
            MemberCreditLedger::TYPE_ADJUSTMENT,
            'Guthabenübernahme aus dem Voranbieter-Export'
        );

        $stats['credit_entries_created']++;
    }

    /**
     * Sum the account balance and the separate consumption credit.
     */
    private function totalCredit(array $data): float
    {
        return round(max(0, $data['balance']['balance']) + max(0, $data['balance']['credit']), 2);
    }

    /**
     * Take over the member card so the existing cards keep working at the
     * scanner after the migration.
     */
    private function importAccessConfig(Member $member, array $data): void
    {
        $nfcUid = $data['access_tags']['nfc_uid'];

        if (! $nfcUid) {
            return;
        }

        // A card identifier must stay unique across the installation; a
        // duplicate is skipped rather than silently reassigned.
        if (MemberAccessConfig::where('nfc_uid', $nfcUid)->exists()) {
            Log::warning('Skipping duplicate NFC identifier during archive import', [
                'member_id' => $member->id,
                'nfc_uid' => $nfcUid,
            ]);

            return;
        }

        MemberAccessConfig::updateOrCreate(
            ['member_id' => $member->id],
            [
                'nfc_uid' => $nfcUid,
                'nfc_enabled' => true,
                'nfc_registered_at' => now(),
                'qr_code_enabled' => true,
            ]
        );
    }

    /**
     * The first charge in gymportal.io covers the period after the one the
     * previous system already collected.
     */
    private function nextChargeDate(?string $paidUntil, ?Carbon $fallback, ?string $endDate = null): ?Carbon
    {
        // A membership that has already ended is never charged again, no matter
        // how far the paid period reaches.
        if ($endDate && Carbon::parse($endDate)->lt(Carbon::today())) {
            return null;
        }

        $next = $paidUntil ? Carbon::parse($paidUntil)->addDay() : $fallback?->copy();

        if (! $next) {
            return null;
        }

        // Do not charge for a period beyond the agreed end of the contract.
        if ($endDate && $next->gt(Carbon::parse($endDate))) {
            return null;
        }

        return $next;
    }

    /**
     * Find an existing member by the original member number or by name plus
     * date of birth, so a repeated import does not create duplicates.
     */
    private function findExistingMember(int $gymId, array $data): ?Member
    {
        $query = Member::where('gym_id', $gymId)
            ->where('first_name', $data['member']['first_name'])
            ->where('last_name', $data['member']['last_name']);

        if ($data['member']['birth_date']) {
            $query->where('birth_date', $data['member']['birth_date']);
        }

        return $query->first();
    }

    /**
     * Match a plan by name first, then by price and billing cycle.
     */
    private function matchPlan($plans, string $name, ?float $price): ?MembershipPlan
    {
        $name = trim($name);

        if ($name !== '') {
            $match = $plans->first(fn ($plan) => mb_strtolower($plan->name) === mb_strtolower($name));

            if ($match) {
                return $match;
            }
        }

        // Matching by price alone would put every free contract onto the first
        // plan that happens to cost nothing, so a named contract keeps its own
        // name instead of being folded into an unrelated free plan.
        if ($price === null || ($price <= 0 && $name !== '')) {
            return null;
        }

        return $plans->first(fn ($plan) => abs((float) $plan->price - $price) < 0.01);
    }

    /**
     * Match an add-on by name.
     */
    private function matchAddon($addons, string $name): ?Addon
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        return $addons->first(fn ($addon) => mb_strtolower($addon->name) === mb_strtolower($name));
    }
}
