<?php

namespace App\Services;

use App\Models\FraudCheck;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Activates memberships taken out as pre-registrations for a gym opening once
 * the opening date is confirmed.
 *
 * Pre-registrations that waited longer than the pending timeout were cancelled
 * by memberships:update-statuses, and the older ones still carry the standard
 * SEPA direct debit from before Mollie was set up. Each membership is moved to
 * the opening date, switched over to Mollie and activated through the regular
 * Mollie flow, which also creates the contract. The member then receives the
 * welcome email with the contract attached.
 *
 * Amounts and due dates of the existing payments are kept as they are, so the
 * member is charged exactly what was agreed at registration.
 */
class PresaleActivationService
{
    public const OUTCOME_READY = 'ready';

    public const OUTCOME_ACTIVATED = 'activated';

    public const OUTCOME_MANDATE_QUEUED = 'mandate_queued';

    public const OUTCOME_SKIPPED = 'skipped';

    public const OUTCOME_FAILED = 'failed';

    private const SEPA_TYPES = ['sepa_direct_debit', 'mollie_directdebit'];

    public function __construct(
        private readonly MollieService $mollieService,
        private readonly MemberService $memberService,
    ) {}

    /**
     * Reason why the gym cannot collect via Mollie, or null when it can.
     */
    public function gymBlockReason(Gym $gym): ?string
    {
        if ($gym->resolvePaymentMethodKey('sepa_direct_debit') !== 'mollie_directdebit') {
            return 'Für dieses Studio ist keine SEPA-Lastschrift über Mollie eingerichtet.';
        }

        return null;
    }

    /**
     * Pre-registrations of the gym that are due for activation.
     *
     * Besides the memberships cancelled by the pending timeout this includes
     * those prepared by an earlier run whose Mollie step did not complete, so
     * the command can simply be run again.
     *
     * @return Collection<int, Membership>
     */
    public function candidates(Gym $gym, Carbon $openingDate): Collection
    {
        return Membership::query()
            ->whereHas('member', fn (Builder $query) => $query->where('gym_id', $gym->id))
            ->whereDate('start_date', '<=', $openingDate->toDateString())
            ->where(function (Builder $query) {
                $query->where(fn (Builder $query) => $query
                    ->where('status', 'expired')
                    ->where('cancellation_reason', Membership::PENDING_TIMEOUT_REASON))
                    ->orWhere(fn (Builder $query) => $query
                        ->where('status', 'pending')
                        ->whereNotNull('metadata->presale_activation'));
            })
            ->with(['member.gym', 'membershipPlan', 'payments'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Activates a single pre-registration, or only checks it on a dry run.
     *
     * @return array{outcome: string, reason: ?string}
     */
    public function process(Membership $membership, Carbon $openingDate, bool $dryRun = false): array
    {
        $paymentMethod = $this->directDebitPaymentMethod($membership);

        $skipReason = $this->skipReason($membership, $paymentMethod, $openingDate);

        if ($skipReason !== null) {
            return $this->result(self::OUTCOME_SKIPPED, $skipReason);
        }

        if ($dryRun) {
            return $this->result(self::OUTCOME_READY, $this->plannedChanges($membership, $openingDate));
        }

        $this->prepare($membership, $paymentMethod, $openingDate);

        return $this->activate($membership, $paymentMethod);
    }

    /**
     * The member's SEPA payment method, or null when there is none or more
     * than one and the right one cannot be told apart safely.
     */
    private function directDebitPaymentMethod(Membership $membership): ?PaymentMethod
    {
        $paymentMethods = $membership->member->paymentMethods()
            ->whereIn('type', self::SEPA_TYPES)
            ->get();

        return $paymentMethods->count() === 1 ? $paymentMethods->first() : null;
    }

    private function skipReason(Membership $membership, ?PaymentMethod $paymentMethod, Carbon $openingDate): ?string
    {
        $member = $membership->member;

        if ($membership->is_free_trial) {
            return 'Gratis-Testzeitraum';
        }

        if ($membership->withdrawn_at !== null) {
            return 'Vertrag wurde widerrufen';
        }

        if ($member->status !== 'pending') {
            return "Mitglied hat den Status \"{$member->status}\"";
        }

        $hasOtherMembership = $member->memberships()
            ->whereKeyNot($membership->id)
            ->whereIn('status', ['active', 'pending', 'paused'])
            ->exists();

        if ($hasOtherMembership) {
            return 'Mitglied hat eine weitere laufende Mitgliedschaft';
        }

        if ($membership->linked_free_membership_id !== null) {
            return 'Mit Gratis-Testzeitraum verknüpft';
        }

        $paymentIssue = $this->paymentIssue($membership);

        if ($paymentIssue !== null) {
            return $paymentIssue;
        }

        if (FraudCheck::where('member_id', $member->id)->where('action', 'flagged')->exists()) {
            return 'Betrugsverdacht markiert, Freigabe erforderlich';
        }

        if ($paymentMethod === null) {
            return 'Keine eindeutige SEPA-Zahlungsart vorhanden';
        }

        if ($paymentMethod->type === 'sepa_direct_debit' && $paymentMethod->sepa_mandate_status !== 'active') {
            return 'SEPA-Mandat ist nicht aktiv';
        }

        if (blank($paymentMethod->iban)) {
            return 'Keine IBAN hinterlegt';
        }

        if ($membership->start_date->lt($openingDate)) {
            if ($membership->discountPhases()->exists()) {
                return 'Rabattphasen hängen am Vertragsbeginn, bitte manuell verschieben';
            }

            if (! $this->hasStandardEndDate($membership)) {
                return 'Vertragsende weicht von der Mindestlaufzeit ab, bitte manuell prüfen';
            }
        }

        return null;
    }

    /**
     * Only untouched, pending payments may be switched over; anything else
     * suggests manual work on the membership that must not be overridden.
     */
    private function paymentIssue(Membership $membership): ?string
    {
        $payments = $membership->payments;

        if ($payments->contains(fn (Payment $payment) => $payment->status === 'canceled')) {
            return 'Stornierte Zahlungen vorhanden, bitte manuell prüfen';
        }

        if ($payments->contains(fn (Payment $payment) => $payment->status !== 'pending')) {
            return 'Zahlungen wurden bereits bearbeitet';
        }

        if ($payments->contains(fn (Payment $payment) => $payment->mollie_payment_id !== null)) {
            return 'Zahlungen wurden bereits an Mollie übergeben';
        }

        if ($payments->contains(fn (Payment $payment) => $payment->payment_method === 'sepa_direct_debit'
            && $payment->invoice_id !== null)) {
            return 'Rechnung zu einer Lastschrift vorhanden';
        }

        return null;
    }

    private function hasStandardEndDate(Membership $membership): bool
    {
        $commitmentEnd = $membership->commitmentEndDate();

        if ($commitmentEnd === null) {
            return $membership->end_date === null;
        }

        return $membership->end_date?->isSameDay($commitmentEnd) ?? false;
    }

    private function plannedChanges(Membership $membership, Carbon $openingDate): string
    {
        $changes = $membership->start_date->lt($openingDate)
            ? ["Vertragsbeginn {$membership->start_date->format('d.m.Y')} → {$openingDate->format('d.m.Y')}"]
            : [];

        $directDebits = $membership->payments->where('payment_method', 'sepa_direct_debit')->count();
        $changes[] = "{$directDebits} Lastschrift(en) auf Mollie umstellen";

        return implode(', ', $changes);
    }

    private function prepare(Membership $membership, PaymentMethod $paymentMethod, Carbon $openingDate): void
    {
        DB::transaction(function () use ($membership, $paymentMethod, $openingDate) {
            $metadata = $membership->metadata ?? [];

            // An earlier, incomplete run already recorded the original state
            $metadata['presale_activation'] ??= [
                'prepared_at' => now()->toISOString(),
                'original_start_date' => $membership->start_date->toDateString(),
                'original_end_date' => $membership->end_date?->toDateString(),
                'original_payment_method' => $paymentMethod->type,
            ];

            $changes = [
                'status' => 'pending',
                'cancellation_reason' => null,
                'metadata' => $metadata,
            ];

            if ($membership->start_date->lt($openingDate)) {
                $commitmentMonths = $membership->membershipPlan->commitment_months;

                $changes['start_date'] = $openingDate->toDateString();
                $changes['end_date'] = $commitmentMonths > 0
                    ? $openingDate->copy()->addMonths($commitmentMonths)->subDay()->toDateString()
                    : null;
            }

            $membership->update($changes);

            if ($paymentMethod->type === 'sepa_direct_debit') {
                $paymentMethod->update([
                    'type' => 'mollie_directdebit',
                    'requires_mandate' => true,
                    'sepa_mandate_data' => array_merge($paymentMethod->sepa_mandate_data ?? [], [
                        'converted_to_mollie_at' => now()->toISOString(),
                    ]),
                ]);
            }

            $offset = $membership->member->gym->getPaymentExecutionOffsets('mollie_directdebit')['initial'];

            $membership->payments()
                ->where('status', 'pending')
                ->where('payment_method', 'sepa_direct_debit')
                ->get()
                ->each(fn (Payment $payment) => $payment->update([
                    'payment_method' => 'mollie_directdebit',
                    'execution_date' => $payment->due_date->copy()->addDays($offset),
                ]));
        });
    }

    /**
     * Hands the payment method to Mollie. With the mandate created right away
     * the member is activated, which creates the contract; a mandate created
     * asynchronously is completed by ActivateMolliePaymentMethod, which sends
     * the welcome email itself.
     *
     * @return array{outcome: string, reason: ?string}
     */
    private function activate(Membership $membership, PaymentMethod $paymentMethod): array
    {
        $member = $membership->member;

        try {
            $handled = $this->mollieService->handleMolliePaymentMethod($member, $paymentMethod->fresh());
        } catch (Throwable $e) {
            report($e);

            return $this->result(self::OUTCOME_FAILED, 'Fehler bei Mollie, erneut ausführen: '.$e->getMessage());
        }

        if ($handled !== true) {
            return $this->result(
                self::OUTCOME_FAILED,
                'Mollie hat das Mandat abgelehnt (z. B. ungültige IBAN), Bankverbindung prüfen und erneut ausführen'
            );
        }

        if ($member->refresh()->status !== 'active') {
            return $this->result(
                self::OUTCOME_MANDATE_QUEUED,
                'Mandat wird bei Mollie angelegt, Aktivierung und Willkommens-E-Mail folgen automatisch'
            );
        }

        $membership->refresh();

        $this->memberService->sendWelcomeEmail($member, $member->gym, $membership->contract_file_path);

        Log::info('Presale membership activated', [
            'membership_id' => $membership->id,
            'member_id' => $member->id,
        ]);

        return $this->result(
            self::OUTCOME_ACTIVATED,
            $membership->contract_file_path ? null : 'Aktiviert, aber kein Vertrag erzeugt (Online-Vertrag deaktiviert?)'
        );
    }

    /**
     * @return array{outcome: string, reason: ?string}
     */
    private function result(string $outcome, ?string $reason = null): array
    {
        return ['outcome' => $outcome, 'reason' => $reason];
    }
}
