<?php

namespace App\Services;

use App\Mail\Dispatching\MemberMailDispatcher;
use App\Mail\DunningNoticeMail;
use App\Models\CollectionCase;
use App\Models\DunningNotice;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Drives the dunning escalation (Mahnwesen) for overdue payments.
 *
 * Levels 1-3 are reached automatically based on the per-gym configuration in
 * {@see Gym::$inkasso_settings}. Level 4 is the handover to the collection
 * partner and is only ever triggered explicitly through the
 * {@see CollectionService}, never by this service.
 */
class DunningService
{
    /** Days the member is given to pay before the next level is considered. */
    protected const PAYMENT_PERIOD_DAYS = 14;

    public function __construct(
        private readonly MemberMailDispatcher $mailDispatcher,
    ) {}

    /** Levels this service escalates automatically. */
    public const AUTOMATIC_LEVELS = [
        DunningNotice::LEVEL_REMINDER,
        DunningNotice::LEVEL_FIRST_NOTICE,
        DunningNotice::LEVEL_SECOND_NOTICE,
    ];

    /**
     * Escalate all eligible members of a gym by at most one level per run.
     *
     * @return array{escalated: int, skipped: int, notices: array<int, DunningNotice>}
     */
    public function processGym(Gym $gym, bool $dryRun = false): array
    {
        $escalated = 0;
        $skipped = 0;
        $notices = [];

        foreach ($this->membersWithOverduePayments($gym) as $member) {
            $notice = $this->escalate($member, $gym, $dryRun);

            if ($notice) {
                $escalated++;
                $notices[] = $notice;
            } else {
                $skipped++;
            }
        }

        return ['escalated' => $escalated, 'skipped' => $skipped, 'notices' => $notices];
    }

    /**
     * Members of the gym that have at least one overdue payment.
     *
     * @return Collection<int, Member>
     */
    public function membersWithOverduePayments(Gym $gym): Collection
    {
        return Member::where('gym_id', $gym->id)
            ->whereHas('payments', fn ($query) => $query->overdue())
            ->with(['payments' => fn ($query) => $query->overdue(), 'dunningNotices'])
            ->get();
    }

    /**
     * Move a member to the next dunning level if the configured waiting time
     * has elapsed. Returns null when nothing was due.
     *
     * While a member is in collection the dunning level must not change, which
     * mirrors the rule shown in the prototype.
     */
    public function escalate(Member $member, ?Gym $gym = null, bool $dryRun = false): ?DunningNotice
    {
        $gym ??= $member->gym;

        if (! $gym) {
            return null;
        }

        if ($this->isInCollection($member)) {
            return null;
        }

        $currentLevel = $member->current_dunning_level;

        if ($currentLevel >= DunningNotice::LEVEL_SECOND_NOTICE) {
            return null;
        }

        $nextLevel = $currentLevel + 1;
        $oldestOverdue = $this->oldestOverduePayment($member);

        if (! $oldestOverdue) {
            return null;
        }

        if (! $this->isLevelDue($member, $gym, $nextLevel, $oldestOverdue)) {
            return null;
        }

        if ($dryRun) {
            return new DunningNotice([
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'level' => $nextLevel,
                'fee' => $gym->getDunningFee($nextLevel),
            ]);
        }

        $notice = DB::transaction(function () use ($member, $gym, $nextLevel, $oldestOverdue) {
            $fee = $gym->getDunningFee($nextLevel);

            $notice = DunningNotice::create([
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'payment_id' => $oldestOverdue->id,
                'level' => $nextLevel,
                'fee' => $fee,
                'triggered_at' => now(),
                'channel' => 'email',
            ]);

            // Book the dunning fee as an additional claim against the member.
            if ($fee > 0) {
                $this->bookDunningFee($member, $gym, $nextLevel, $fee);
            }

            if ($member->status === 'active') {
                $member->update(['status' => 'overdue']);
            }

            // Reset the cached relation so a follow-up call sees the new level.
            $member->unsetRelation('dunningNotices');

            Log::info('Dunning level reached', [
                'member_id' => $member->id,
                'gym_id' => $gym->id,
                'level' => $nextLevel,
                'fee' => $fee,
            ]);

            return $notice;
        });

        // Only notify once the level and the fee are committed: a mail failure
        // must not roll back the escalation, and the member must never be
        // asked to pay for a notice that was not persisted.
        $this->sendNotice($member, $gym, $notice);

        return $notice;
    }

    /**
     * Mail the notice to the member. Delivery problems are logged by the
     * dispatcher and never interrupt the dunning run.
     */
    protected function sendNotice(Member $member, Gym $gym, DunningNotice $notice): void
    {
        $this->mailDispatcher->sendToMember(
            $member,
            new DunningNoticeMail(
                $member,
                $gym,
                $notice->level,
                $this->placeholderData($member, $notice),
            ),
        );
    }

    /**
     * Values behind the dunning placeholders of the mail template.
     *
     * The open amount is read after the fee was booked, so the fee is part of
     * the total but is also shown separately.
     *
     * @return array<string, string>
     */
    protected function placeholderData(Member $member, DunningNotice $notice): array
    {
        $fee = (float) $notice->fee;
        $total = round((float) $member->payments()->overdue()->sum('amount'), 2);
        $open = round($total - $fee, 2);
        $oldest = $this->oldestOverduePayment($member);

        return [
            '[Offener-Betrag]' => $this->money($open),
            '[Mahngebuehr]' => $this->money($fee),
            '[Gesamtbetrag]' => $this->money($total),
            '[Faelligkeitsdatum]' => optional($oldest?->overdue_since)->format('d.m.Y') ?? '',
            '[Zahlungsfrist]' => Carbon::today()->addDays(self::PAYMENT_PERIOD_DAYS)->format('d.m.Y'),
        ];
    }

    protected function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }

    /**
     * Create the fee as a pending payment so it shows up as an open claim.
     */
    protected function bookDunningFee(Member $member, Gym $gym, int $level, float $fee): Payment
    {
        return Payment::create([
            'gym_id' => $gym->id,
            'member_id' => $member->id,
            'amount' => $fee,
            'currency' => 'EUR',
            'description' => 'Mahngebühr Stufe '.$level,
            'status' => 'pending',
            'due_date' => Carbon::today(),
            'execution_date' => Carbon::today(),
            'payment_method' => 'dunning_fee',
            'metadata' => ['dunning_level' => $level],
        ]);
    }

    /**
     * Whether the waiting time configured for the level has elapsed.
     *
     * Level 1 counts from the point the oldest overdue payment actually became
     * overdue — the later one of its due date and its execution date — every
     * further level counts from the previous notice.
     */
    protected function isLevelDue(Member $member, Gym $gym, int $level, Payment $oldestOverdue): bool
    {
        $triggerDays = $this->triggerDays($gym, $level);

        if ($triggerDays === null) {
            return false;
        }

        $reference = $level === DunningNotice::LEVEL_REMINDER
            ? $oldestOverdue->overdue_since
            : optional($this->lastNotice($member))->triggered_at;

        if (! $reference) {
            return false;
        }

        return Carbon::parse($reference)->addDays($triggerDays)->startOfDay()->lte(Carbon::now());
    }

    protected function triggerDays(Gym $gym, int $level): ?int
    {
        foreach ($gym->inkasso_settings['levels'] ?? [] as $config) {
            if ((int) ($config['level'] ?? 0) === $level) {
                $days = $config['trigger_days'] ?? null;

                return $days === null ? null : (int) $days;
            }
        }

        return null;
    }

    protected function lastNotice(Member $member): ?DunningNotice
    {
        return $member->dunningNotices()->orderByDesc('level')->first();
    }

    /**
     * The overdue payment that started running first, measured by the same
     * reference the escalation uses: the later of due date and execution date.
     */
    protected function oldestOverduePayment(Member $member): ?Payment
    {
        return $member->payments()
            ->overdue()
            ->reorder()
            ->get()
            ->sortBy(fn (Payment $payment) => $payment->overdue_since)
            ->first();
    }

    /**
     * The member currently has an open collection case.
     */
    public function isInCollection(Member $member): bool
    {
        return CollectionCase::where('member_id', $member->id)
            ->whereIn('status', CollectionCase::OPEN_STATUSES)
            ->exists();
    }

    /**
     * Members that completed the dunning process and can be handed over.
     *
     * Each entry carries the reason why a member cannot be handed over yet
     * (minimum amount, minor without legal guardian) so the UI can show it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function readyForCollection(Gym $gym): array
    {
        $settings = $gym->inkasso_settings;
        $minAmount = (float) ($settings['min_amount'] ?? 0);
        $includeMinors = (bool) ($settings['include_minors'] ?? false);

        $members = Member::where('gym_id', $gym->id)
            ->whereHas('dunningNotices', fn ($q) => $q->where('level', '>=', DunningNotice::LEVEL_SECOND_NOTICE))
            ->whereDoesntHave('collectionCases', fn ($q) => $q->whereIn('status', CollectionCase::OPEN_STATUSES))
            ->with(['payments' => fn ($q) => $q->overdue()])
            ->get();

        $rows = [];

        foreach ($members as $member) {
            $open = round((float) $member->payments->sum('amount'), 2);

            $rows[] = [
                'member' => $member,
                'claims' => $member->payments->count(),
                'open_amount' => $open,
                'level' => $member->current_dunning_level,
                'block' => $this->handoverBlocker($member, $open, $minAmount, $includeMinors),
            ];
        }

        return $rows;
    }

    /**
     * Reason preventing the handover, or null when the member can be handed over.
     */
    public function handoverBlocker(Member $member, float $openAmount, float $minAmount, bool $includeMinors): ?string
    {
        if ($openAmount < $minAmount) {
            return 'Unter Mindestbetrag';
        }

        if ($this->isMinor($member) && ! $includeMinors && ! $member->legal_guardian_member_id) {
            return 'Minderjährig';
        }

        return null;
    }

    public function isMinor(Member $member): bool
    {
        return $member->birth_date !== null
            && Carbon::parse($member->birth_date)->age < 18;
    }

    /**
     * Reset the dunning state after a collection case was closed or cancelled.
     */
    public function resetForMember(Member $member): void
    {
        $member->dunningNotices()->delete();

        if ($member->status === 'overdue') {
            $member->update(['status' => 'active']);
        }
    }
}
