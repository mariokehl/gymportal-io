<?php

namespace App\Services;

use App\Models\CollectionCase;
use App\Models\CollectionClaim;
use App\Models\CollectionPayment;
use App\Models\CollectionRun;
use App\Models\DunningNotice;
use App\Models\Gym;
use App\Models\Member;
use App\Services\Diagonal\DiagonalApiException;
use App\Services\Diagonal\DiagonalCaseMapper;
use App\Services\Diagonal\DiagonalClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handles the debt collection ("Inkasso") lifecycle: handing members over to
 * the partner, booking reported payments, closing, cancelling and undoing runs.
 */
class CollectionService
{
    public function __construct(
        protected DunningService $dunningService,
        protected CollectionPaymentAllocator $allocator,
        protected DiagonalClient $diagonalClient,
        protected DiagonalCaseMapper $diagonalMapper,
    ) {}

    /**
     * Transmit a case to the partner and store the returned GUID.
     *
     * Transmission failures never roll back the local handover: the case stays
     * recorded and can be retransmitted once the cause is fixed.
     */
    public function transmitCase(CollectionCase $case, ?Gym $gym = null): ?string
    {
        $gym ??= $case->gym;

        try {
            $payload = $this->diagonalMapper->toFileDataItem($case, $gym);
            $guid = $this->diagonalClient->addFile($gym, $payload);

            $case->update([
                'diagonal_guid' => $guid,
                'diagonal_state' => 'submitted',
                'diagonal_synced_at' => now(),
            ]);

            return $guid;
        } catch (DiagonalApiException $e) {
            $case->update([
                'diagonal_state' => 'error',
                'notes' => trim(($case->notes ?? "\n").'Übertragung fehlgeschlagen: '.$e->getMessage()),
            ]);

            Log::warning('Failed to transmit collection case to DIAGONAL', [
                'case_id' => $case->id,
                'gym_id' => $gym->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Hand a set of members over to the partner as one run.
     *
     * @param  array<int, int>  $memberIds
     */
    public function createRun(Gym $gym, array $memberIds, ?int $userId = null): CollectionRun
    {
        if (! $gym->isInkassoEnabled()) {
            throw new RuntimeException('Es ist kein Inkassopartner aktiv.');
        }

        $eligible = $this->eligibleMembersForHandover($gym, $memberIds);

        if ($eligible === []) {
            throw new RuntimeException('Keine übergabefähigen Mitglieder ausgewählt.');
        }

        return DB::transaction(function () use ($gym, $eligible, $userId) {
            $run = CollectionRun::create([
                'gym_id' => $gym->id,
                'run_number' => CollectionRun::generateRunNumber($gym->id),
                'partner' => $gym->inkasso_settings['partner'] ?? 'diagonal',
                'handed_over_at' => now(),
                'status' => CollectionRun::STATUS_HANDED_OVER,
                'created_by' => $userId,
            ]);

            foreach ($eligible as $member) {
                $this->handOverMember($member, $gym, $run);
            }

            return $this->recalculateRunTotals($run);
        });
    }

    /**
     * Transmit every not yet submitted case of a run to the partner.
     *
     * @return array{transmitted: int, failed: int}
     */
    public function transmitRun(CollectionRun $run): array
    {
        $transmitted = 0;
        $failed = 0;

        foreach ($run->cases()->whereNull('diagonal_guid')->get() as $case) {
            $this->transmitCase($case, $run->gym) ? $transmitted++ : $failed++;
        }

        return ['transmitted' => $transmitted, 'failed' => $failed];
    }

    /**
     * Hand a single member over, optionally as part of a run.
     */
    public function handOverMember(Member $member, ?Gym $gym = null, ?CollectionRun $run = null, ?float $dunningFeeOverride = null): CollectionCase
    {
        $gym ??= $member->gym;

        if (! $gym->isInkassoEnabled()) {
            throw new RuntimeException('Es ist kein Inkassopartner aktiv.');
        }

        if ($this->dunningService->isInCollection($member)) {
            throw new RuntimeException('Das Mitglied befindet sich bereits im Inkasso.');
        }

        return DB::transaction(function () use ($member, $gym, $run, $dunningFeeOverride) {
            $settings = $gym->inkasso_settings;
            $flatFee = (float) ($settings['handover_flat_fee'] ?? 0);

            $case = CollectionCase::create([
                'gym_id' => $gym->id,
                'collection_run_id' => $run?->id,
                'member_id' => $member->id,
                'case_number' => CollectionCase::generateCaseNumber($gym->id),
                'status' => CollectionCase::STATUS_IN_PROGRESS,
                'handed_over_at' => now(),
            ]);

            $overduePayments = $member->payments()->overdue()->orderBy('due_date')->get();

            foreach ($overduePayments as $payment) {
                CollectionClaim::create([
                    'gym_id' => $gym->id,
                    'collection_case_id' => $case->id,
                    'payment_id' => $payment->id,
                    'description' => $payment->description ?: 'Offene Forderung',
                    'due_date' => $payment->due_date,
                    'amount' => $payment->amount,
                    'kind' => ($payment->metadata['dunning_level'] ?? null)
                        ? CollectionClaim::KIND_DUNNING
                        : CollectionClaim::KIND_PRINCIPAL,
                ]);

                // The claim now lives in the collection case, so it must no
                // longer be collected by the studio itself.
                $payment->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'notes' => trim(($payment->notes ?? '')."\nAn Inkasso übergeben: {$case->case_number}"),
                ]);
            }

            // An explicit dunning fee chosen at handover time.
            if ($dunningFeeOverride !== null && $dunningFeeOverride > 0) {
                CollectionClaim::create([
                    'gym_id' => $gym->id,
                    'collection_case_id' => $case->id,
                    'description' => 'Mahngebühr bei Übergabe',
                    'due_date' => Carbon::today(),
                    'amount' => $dunningFeeOverride,
                    'kind' => CollectionClaim::KIND_DUNNING,
                ]);
            }

            if ($flatFee > 0) {
                CollectionClaim::create([
                    'gym_id' => $gym->id,
                    'collection_case_id' => $case->id,
                    'description' => 'Übergabepauschale Inkasso',
                    'due_date' => Carbon::today(),
                    'amount' => $flatFee,
                    'kind' => CollectionClaim::KIND_FLAT,
                ]);
            }

            $this->recalculateCaseTotals($case);

            // Level 4 marks the collection handover and blocks further dunning.
            DunningNotice::create([
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'level' => DunningNotice::LEVEL_COLLECTION,
                'fee' => $flatFee,
                'triggered_at' => now(),
                'channel' => 'collection',
                'meta' => ['case_number' => $case->case_number],
            ]);

            // Handing a member over always blocks their access.
            $member->update(['status' => 'blocked']);

            Log::info('Member handed over to collection', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'case_number' => $case->case_number,
            ]);

            return $case->fresh(['claims']);
        });
    }

    /**
     * Members that may be handed over, filtered by the gym's rules.
     *
     * @param  array<int, int>  $memberIds
     * @return array<int, Member>
     */
    public function eligibleMembersForHandover(Gym $gym, array $memberIds): array
    {
        $settings = $gym->inkasso_settings;
        $minAmount = (float) ($settings['min_amount'] ?? 0);
        $includeMinors = (bool) ($settings['include_minors'] ?? false);

        $members = Member::where('gym_id', $gym->id)
            ->whereIn('id', $memberIds)
            ->whereDoesntHave('collectionCases', fn ($q) => $q->whereIn('status', CollectionCase::OPEN_STATUSES))
            ->with(['payments' => fn ($q) => $q->overdue()])
            ->get();

        $eligible = [];

        foreach ($members as $member) {
            $open = round((float) $member->payments->sum('amount'), 2);
            $blocker = $this->dunningService->handoverBlocker($member, $open, $minAmount, $includeMinors);

            if ($blocker === null) {
                $eligible[] = $member;
            }
        }

        return $eligible;
    }

    /**
     * Book a payment reported by the partner and distribute it across claims.
     *
     * @param  array<int, float>|null  $manualAllocation
     */
    public function bookPayment(
        CollectionCase $case,
        float $amount,
        Carbon $bookedAt,
        string $mode = CollectionPayment::MODE_AUTO,
        ?array $manualAllocation = null,
        bool $closeCase = false,
        ?int $userId = null,
        string $source = 'Meldung Inkassopartner',
    ): CollectionPayment {
        $allocation = $mode === CollectionPayment::MODE_MANUAL
            ? $this->allocator->normaliseManual($case, $manualAllocation ?? [])
            : $this->allocator->allocateAutomatically($case, $amount);

        if (! $this->allocator->validate($case, $allocation, $amount)) {
            throw new RuntimeException('Die Verteilung stimmt nicht mit dem Zahlungsbetrag überein.');
        }

        return DB::transaction(function () use ($case, $amount, $bookedAt, $mode, $allocation, $closeCase, $userId, $source) {
            foreach ($allocation as $claimId => $allocated) {
                $claim = $case->claims()->whereKey($claimId)->first();

                if ($claim) {
                    $claim->update([
                        'paid_amount' => round((float) $claim->paid_amount + $allocated, 2),
                    ]);
                }
            }

            $payment = CollectionPayment::create([
                'gym_id' => $case->gym_id,
                'collection_case_id' => $case->id,
                'booked_at' => $bookedAt,
                'amount' => round($amount, 2),
                'allocation_mode' => $mode,
                'source' => $source,
                'allocation' => $allocation,
                'created_by' => $userId,
            ]);

            $case->refresh();
            $this->recalculateCaseTotals($case);

            $this->reportPaymentToPartner($payment, $case);

            if ($closeCase) {
                $this->closeCase($case, 'Fall mit Zahlung abgeschlossen.');
            } else {
                $case->update([
                    'status' => (float) $case->paid_amount > 0
                        ? CollectionCase::STATUS_PARTIAL_PAYMENT
                        : CollectionCase::STATUS_IN_PROGRESS,
                ]);
            }

            return $payment;
        });
    }

    /**
     * Report a booked payment to the partner. Failures are logged but never
     * block the local booking.
     */
    protected function reportPaymentToPartner(CollectionPayment $payment, CollectionCase $case): void
    {
        if (! $case->diagonal_guid) {
            return;
        }

        try {
            $guid = $this->diagonalClient->addPayment(
                $case->gym,
                $this->diagonalMapper->toPaymentDataItem($payment, $case)
            );

            $payment->update(['diagonal_guid' => $guid, 'diagonal_state' => 'submitted']);
        } catch (DiagonalApiException $e) {
            $payment->update(['diagonal_state' => 'error']);

            Log::warning('Failed to report payment to DIAGONAL', [
                'collection_payment_id' => $payment->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ask the partner to stop working on a case. Failures are logged so the
     * local cancellation still succeeds; the studio is legally required to
     * inform the partner, which the UI points out.
     */
    protected function reportCancellationToPartner(CollectionCase $case, string $reason, ?string $information = null): void
    {
        if (! $case->diagonal_guid) {
            return;
        }

        try {
            $this->diagonalClient->cancelFile(
                $case->gym,
                $this->diagonalMapper->toCancellationItem($case, $reason, $information)
            );
        } catch (DiagonalApiException $e) {
            Log::warning('Failed to cancel case at DIAGONAL', [
                'case_id' => $case->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Close a case because the partner finished working on it.
     */
    public function closeCase(CollectionCase $case, ?string $note = null): CollectionCase
    {
        return DB::transaction(function () use ($case, $note) {
            $gym = $case->gym;
            $residual = $gym->inkasso_settings['residual_handling'] ?? Gym::RESIDUAL_ALWAYS_WRITE_OFF;

            if ($residual === Gym::RESIDUAL_ALWAYS_WRITE_OFF) {
                $case->claims()
                    ->where('written_off', false)
                    ->get()
                    ->each(function (CollectionClaim $claim) {
                        if ((float) $claim->open_amount > CollectionPaymentAllocator::EPSILON) {
                            $claim->update(['written_off' => true]);
                        }
                    });
            }

            $case->update([
                'status' => CollectionCase::STATUS_COMPLETED,
                'closed_at' => now(),
                'notes' => $note ?: $case->notes,
            ]);

            $this->releaseMember($case);

            return $case->fresh();
        });
    }

    /**
     * Cancel a case: the claims are released for further processing.
     */
    public function cancelCase(CollectionCase $case, ?string $note = null): CollectionCase
    {
        $this->reportCancellationToPartner($case, 'Goodwill', $note);

        return DB::transaction(function () use ($case, $note) {
            $case->update([
                'status' => CollectionCase::STATUS_CANCELLED,
                'closed_at' => now(),
                'notes' => $note ?: $case->notes,
            ]);

            $this->releaseMember($case);

            return $case->fresh();
        });
    }

    /**
     * Record a rejection reported by the partner.
     */
    public function rejectCase(CollectionCase $case, string $reason): CollectionCase
    {
        return DB::transaction(function () use ($case, $reason) {
            $gym = $case->gym;
            $residual = $gym->inkasso_settings['residual_handling'] ?? Gym::RESIDUAL_ALWAYS_WRITE_OFF;

            if ($residual === Gym::RESIDUAL_ALWAYS_WRITE_OFF) {
                $case->claims()->where('written_off', false)->update(['written_off' => true]);
            }

            $case->update([
                'status' => CollectionCase::STATUS_REJECTED,
                'closed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->releaseMember($case);

            return $case->fresh();
        });
    }

    /**
     * Undo a whole run: every member leaves the collection status while the
     * dunning levels they reached before the handover stay untouched.
     */
    public function undoRun(CollectionRun $run): CollectionRun
    {
        // The partner has to be told to stop working on every case of the run.
        foreach ($run->cases()->whereNotNull('diagonal_guid')->get() as $case) {
            $this->reportCancellationToPartner($case, 'Goodwill', "Lauf {$run->run_number} rückgängig gemacht.");
        }

        return DB::transaction(function () use ($run) {
            foreach ($run->cases as $case) {
                if ($case->is_open) {
                    $case->update([
                        'status' => CollectionCase::STATUS_CANCELLED,
                        'closed_at' => now(),
                        'notes' => trim(($case->notes ?? '')."\nLauf {$run->run_number} rückgängig gemacht."),
                    ]);
                }

                $this->releaseMember($case, keepDunningLevel: true);
            }

            $run->update(['status' => CollectionRun::STATUS_CANCELLED]);

            Log::info('Collection run undone', ['run_id' => $run->id, 'run_number' => $run->run_number]);

            return $run->fresh();
        });
    }

    /**
     * Take the member out of the collection state.
     *
     * The level 4 notice is always removed so the member is no longer counted
     * as "in collection"; levels 1-3 are kept unless the case was completed.
     */
    protected function releaseMember(CollectionCase $case, bool $keepDunningLevel = false): void
    {
        $member = $case->member;

        if (! $member) {
            return;
        }

        $member->dunningNotices()
            ->where('level', DunningNotice::LEVEL_COLLECTION)
            ->delete();

        if (! $keepDunningLevel && $case->status === CollectionCase::STATUS_COMPLETED) {
            // A completed case resets the dunning process entirely.
            $this->dunningService->resetForMember($member->fresh());

            return;
        }

        if ($member->status === 'blocked') {
            $member->update(['status' => 'overdue']);
        }
    }

    /**
     * Recalculate the cached sums of a case from its claims.
     */
    public function recalculateCaseTotals(CollectionCase $case): CollectionCase
    {
        $claims = $case->claims()->get();

        $case->update([
            'principal_amount' => $claims->where('kind', CollectionClaim::KIND_PRINCIPAL)->sum('amount'),
            'dunning_amount' => $claims->where('kind', CollectionClaim::KIND_DUNNING)->sum('amount'),
            'flat_amount' => $claims->where('kind', CollectionClaim::KIND_FLAT)->sum('amount'),
            'paid_amount' => $claims->sum('paid_amount'),
        ]);

        return $case;
    }

    /**
     * Recalculate the cached sums of a run from its cases.
     */
    public function recalculateRunTotals(CollectionRun $run): CollectionRun
    {
        $cases = $run->cases()->get();

        $principal = $cases->sum(fn (CollectionCase $case) => (float) $case->principal_amount);
        $dunning = $cases->sum(fn (CollectionCase $case) => (float) $case->dunning_amount);
        $flat = $cases->sum(fn (CollectionCase $case) => (float) $case->flat_amount);

        $run->update([
            'member_count' => $cases->count(),
            'principal_amount' => $principal,
            'dunning_amount' => $dunning,
            'flat_amount' => $flat,
            'total_amount' => round($principal + $dunning + $flat, 2),
        ]);

        return $run->fresh();
    }

    /**
     * Aggregated figures for the collection overview.
     *
     * @return array<string, mixed>
     */
    public function statistics(Gym $gym): array
    {
        $openCases = CollectionCase::where('gym_id', $gym->id)
            ->whereIn('status', CollectionCase::OPEN_STATUSES)
            ->get();

        $rejected = CollectionCase::where('gym_id', $gym->id)
            ->where('status', CollectionCase::STATUS_REJECTED)
            ->count();

        $recovered = CollectionPayment::where('gym_id', $gym->id)
            ->whereYear('booked_at', now()->year)
            ->sum('amount');

        return [
            'in_collection_count' => $openCases->count(),
            'in_collection_amount' => round($openCases->sum(fn (CollectionCase $c) => (float) $c->total_amount), 2),
            'rejected_count' => $rejected,
            'recovered_amount' => round((float) $recovered, 2),
        ];
    }
}
