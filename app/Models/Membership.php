<?php

namespace App\Models;

use App\Events\MembershipActivated;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Membership extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_id',
        'membership_plan_id',
        'start_date',
        'end_date',
        'status',
        'pause_start_date',
        'pause_end_date',
        'cancellation_date',
        'cancellation_reason',
        'contract_file_path',
        'metadata',
        'notes',
        'linked_free_membership_id',
        // Widerrufs-Felder (§ 356a BGB)
        'withdrawn_at',
        'withdrawal_confirmation_sent_to',
        'withdrawal_refund_amount',
        'withdrawal_refund_processed_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'pause_start_date' => 'date:Y-m-d',
        'pause_end_date' => 'date:Y-m-d',
        'cancellation_date' => 'date:Y-m-d',
        'metadata' => 'array',
        // Widerrufs-Felder (§ 356a BGB)
        'withdrawn_at' => 'datetime',
        'withdrawal_refund_processed_at' => 'datetime',
        'withdrawal_refund_amount' => 'decimal:2',
    ];

    protected $appends = [
        'min_cancellation_date',
        'default_cancellation_date',
        'cancellation_deadline',
        'projected_end_date',
        'can_cancel',
        'is_free_trial',
        // Widerrufs-Attribute (§ 356a BGB)
        'withdrawal_eligible',
        'withdrawal_deadline',
        'contract_start_date',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class)
            ->withPivot(
                'mode',
                'price',
                'payment_id',
                'completed_at',
                'completed_by',
                'cancelled_at',
                'cancellation_effective_at',
                'cancelled_by'
            )
            ->withTimestamps();
    }

    /**
     * Add-ons the member may currently use.
     *
     * A cancelled add-on stays usable until the end of the period it was last
     * billed for — the same boundary ProcessMembershipPayments applies, so the
     * member gets what they paid for. Add-ons deactivated gym-wide are excluded.
     */
    public function activeAddons(): BelongsToMany
    {
        return $this->addons()
            ->where('addons.is_active', true)
            ->where(function ($query) {
                $query->whereNull('addon_membership.cancellation_effective_at')
                    ->orWhereDate(
                        'addon_membership.cancellation_effective_at',
                        '>=',
                        now()->startOfDay()->toDateString()
                    );
            });
    }

    /**
     * Whether a specific add-on is currently usable on this membership.
     */
    public function hasActiveAddon(int $addonId): bool
    {
        return $this->activeAddons()->where('addons.id', $addonId)->exists();
    }

    /**
     * Verknüpfte Gratis-Mitgliedschaft (bei Vertragsstart zum 1. des Monats)
     */
    public function linkedFreeMembership()
    {
        return $this->belongsTo(Membership::class, 'linked_free_membership_id');
    }

    /**
     * Verknüpfte zahlungspflichtige Mitgliedschaft (inverse Relation)
     */
    public function linkedPaidMembership()
    {
        return $this->hasOne(Membership::class, 'linked_free_membership_id');
    }

    /**
     * Prüft ob dies eine Gratis-Testmitgliedschaft ist
     */
    public function getIsFreeTrialAttribute(): bool
    {
        return $this->membershipPlan?->is_free_trial_plan ?? false;
    }

    public function getStatusTextAttribute()
    {
        return [
            'active' => 'Aktiv',
            'paused' => 'Pausiert',
            'cancelled' => 'Gekündigt',
            'expired' => 'Abgelaufen',
            'pending' => 'Ausstehend',
            'withdrawn' => 'Widerrufen',
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'active' => 'green',
            'paused' => 'yellow',
            'cancelled' => 'red',
            'expired' => 'gray',
            'pending' => 'orange',
            'withdrawn' => 'purple',
        ][$this->status] ?? 'gray';
    }

    public function getDurationInMonthsAttribute()
    {
        if (! $this->end_date) {
            return null;
        }

        return $this->start_date->diffInMonths($this->end_date);
    }

    public function getIsPausedAttribute()
    {
        return $this->status === 'paused';
    }

    /**
     * Check whether the given date falls into the scheduled pause period.
     *
     * Both boundaries are inclusive: the pause covers the start and the end day
     * in full. A membership without a complete pause period is never paused for
     * a date, independent of its current status.
     */
    public function isDateWithinPause(Carbon $date): bool
    {
        if (! $this->pause_start_date || ! $this->pause_end_date) {
            return false;
        }

        return $date->copy()->startOfDay()->betweenIncluded(
            $this->pause_start_date->copy()->startOfDay(),
            $this->pause_end_date->copy()->startOfDay()
        );
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active';
    }

    public function getIsCancelledAttribute()
    {
        return $this->status === 'cancelled';
    }

    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    public function activateMembership(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $result = $this->update(['status' => 'active']);

        if ($result) {
            MembershipActivated::dispatch($this);
        }

        return $result;
    }

    public function getNextPaymentAttribute()
    {
        return $this->payments()->where('status', 'pending')->orderBy('due_date')->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    /**
     * Scope: Mitgliedschaften, die als expired markiert werden sollten.
     * Erst expired wenn der komplette End-Tag vergangen ist (end_date < today).
     */
    public function scopeShouldBeExpired($query)
    {
        return $query->whereIn('status', ['active', 'paused'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', Carbon::today())
            ->whereNull('cancellation_date');
    }

    /**
     * Markiert diese Mitgliedschaft als expired und deaktiviert ggf. das Mitglied.
     */
    public function markAsExpired(string $source = 'system'): void
    {
        if ($this->status === 'expired') {
            return;
        }

        $this->update([
            'status' => 'expired',
            'metadata' => array_merge($this->metadata ?? [], [
                'expired_at' => now()->toDateTimeString(),
                'expired_by' => $source,
            ]),
        ]);

        $member = $this->member;
        if ($member && ! $member->memberships()->where('status', 'active')->exists()) {
            $member->update(['status' => 'inactive']);
        }

        Log::info("Membership expired by {$source}", [
            'membership_id' => $this->id,
            'member_id' => $this->member_id,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Start of the monthly billing period that contains $reference.
     *
     * Recurring charges are anchored to the membership start date, so periods
     * run from the start day of one month to the day before the start day of
     * the next. Only when the contract itself started on the 1st (e.g. because
     * the gym runs "Verträge immer zum 1. des Monats starten") do these periods
     * line up with calendar months. Shorter months clamp to their last day.
     */
    public function billingPeriodStart(?Carbon $reference = null): ?Carbon
    {
        if (! $this->start_date) {
            return null;
        }

        $reference = ($reference ?? Carbon::today())->copy()->startOfDay();
        $start = $this->start_date->copy()->startOfDay();

        if ($reference->lte($start)) {
            return $start;
        }

        $anchorDay = $start->day;

        $periodStart = $reference->copy()->startOfMonth();
        $periodStart->day = min($anchorDay, $periodStart->daysInMonth);

        // The anchor day has not been reached yet this month, so the current
        // period still belongs to the previous month.
        if ($periodStart->gt($reference)) {
            $periodStart = $reference->copy()->startOfMonth()->subMonth();
            $periodStart->day = min($anchorDay, $periodStart->daysInMonth);
        }

        return $periodStart;
    }

    /**
     * Last day of the monthly billing period that contains $reference — the day
     * before the next charge. This is the effective date for a cancellation
     * "to the end of the current period".
     */
    public function billingPeriodEnd(?Carbon $reference = null): ?Carbon
    {
        $periodStart = $this->billingPeriodStart($reference);

        if (! $periodStart) {
            return null;
        }

        $anchorDay = $this->start_date->day;

        $nextStart = $periodStart->copy()->startOfMonth()->addMonth();
        $nextStart->day = min($anchorDay, $nextStart->daysInMonth);

        return $nextStart->subDay();
    }

    public function getMinCancellationDateAttribute()
    {
        if (! $this->start_date || ! $this->membershipPlan) {
            return null;
        }

        $date = $this->start_date->copy();

        // Add commitment months if defined
        if ($this->membershipPlan->commitment_months) {
            $date->addMonths($this->membershipPlan->commitment_months);
        }

        // Subtract cancellation period (user must cancel before commitment ends)
        if ($this->membershipPlan->cancellation_period) {
            if ($this->membershipPlan->cancellation_period_unit === 'months') {
                $date->subMonths($this->membershipPlan->cancellation_period);
            } else {
                $date->subDays($this->membershipPlan->cancellation_period);
            }
        }

        return $date->format('Y-m-d');
    }

    public function getDefaultCancellationDateAttribute()
    {
        // If membership is already cancelled, return the existing cancellation_date
        if ($this->status === 'cancelled' && $this->cancellation_date) {
            return $this->cancellation_date->format('Y-m-d');
        }

        // Unbefristete Mitgliedschaft: Kündigungsdatum = heute + Kündigungsfrist aus Plan
        if ($this->end_date === null && $this->isInitialTermCompleted()) {
            $period = $this->membershipPlan->cancellation_period ?? 1;
            $unit = $this->membershipPlan->cancellation_period_unit ?? 'months';

            if ($unit === 'months') {
                return now()->addMonths($period)->format('Y-m-d');
            }

            return now()->addDays($period)->format('Y-m-d');
        }

        // Always use end_date if available
        if ($this->end_date) {
            return $this->end_date->format('Y-m-d');
        }

        // Fallback to min_cancellation_date if no end_date exists
        return $this->min_cancellation_date;
    }

    /**
     * Latest possible cancellation date before the contract auto-renews.
     *
     * Derived from end_date (the end of the current term) minus the plan's
     * cancellation period. The extra day (addDay) anchors the deadline to the
     * start of the following period.
     */
    public function getCancellationDeadlineAttribute(): ?string
    {
        if (! $this->end_date) {
            return null;
        }

        $plan = $this->membershipPlan;
        $cancellationPeriod = $plan->cancellation_period ?? 1;
        $cancellationUnit = $plan->cancellation_period_unit ?? 'months';

        if ($cancellationUnit === 'months') {
            $cancellationDeadline = $this->end_date->copy()->addDay()->subMonths($cancellationPeriod);
        } else {
            $cancellationDeadline = $this->end_date->copy()->addDay()->subDays($cancellationPeriod);
        }

        return $cancellationDeadline->format('Y-m-d');
    }

    /**
     * End date of the currently relevant term, projected without relying on the
     * database state.
     *
     * As long as the renewal is not yet due this equals the stored end_date. Once
     * the cancellation deadline is reached the contract is bound to auto-renew, so
     * this returns the end date the daily renewal cron will write — allowing the
     * UI to show the correct term end even in the window before the cron runs
     * (e.g. between 00:00 and 02:00 on the deadline day). Mirrors the calculation
     * in ProcessMembershipPayments::renewMembership().
     */
    public function getProjectedEndDateAttribute(): ?string
    {
        if (! $this->end_date) {
            return null;
        }

        // Renewal not due yet: the stored end_date is still the current term end.
        $cancellationDeadline = $this->cancellation_deadline;
        if ($cancellationDeadline === null || Carbon::today()->lt(Carbon::parse($cancellationDeadline))) {
            return $this->end_date->format('Y-m-d');
        }

        // Renewal is due: project the new end date the same way the cron would.
        // Renewal is always indefinite or monthly, regardless of the initial term.
        $autoRenewType = $this->membershipPlan->auto_renew_type ?? 'indefinite';

        // Indefinite rollover clears the end date entirely.
        if ($autoRenewType === 'indefinite') {
            return null;
        }

        // Monthly rollover: extend by one month.
        return $this->end_date->copy()->addDay()->addMonths(1)->subDay()->format('Y-m-d');
    }

    public function getCanCancelAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    public function canBeCancelled(): bool
    {
        if (! $this->start_date || ! $this->membershipPlan) {
            return false;
        }

        $today = now()->startOfDay();
        $start = $this->start_date->copy()->startOfDay();

        $commitmentMonths = $this->membershipPlan->commitment_months ?? 0;

        // 1. End of minimum term
        $commitmentEnd = $start->copy()->addMonths($commitmentMonths);

        // If no cancellation is possible during the minimum contract period
        if ($today->lessThan($commitmentEnd)) {
            return false;
        }

        // 2. Unbefristete Mitgliedschaft (end_date=null): Immer kündbar nach Erstlaufzeit
        // Gemäß Gesetz für faire Verbraucherverträge (ab 01.03.2022)
        if ($this->end_date === null) {
            return true;
        }

        // 3. We are after the minimum term → extension periods apply
        $renewalMonths = $this->membershipPlan->renewal_months ?? 1;
        $cancellationPeriod = $this->membershipPlan->cancellation_period ?? 0;
        $cancellationPeriodUnit = $this->membershipPlan->cancellation_period_unit ?? 'days';

        $periodStart = $commitmentEnd->copy();

        // Find current or next renewal period
        while ($periodStart->lessThanOrEqualTo($today)) {
            $periodStart->addMonths($renewalMonths);
        }

        $periodEnd = $periodStart->copy();

        // 4. Calculate the latest possible termination date for this period.
        if ($cancellationPeriodUnit === 'months') {
            $latestPossibleCancellation = $periodEnd->copy()->subMonths($cancellationPeriod);
        } else {
            $latestPossibleCancellation = $periodEnd->copy()->subDays($cancellationPeriod);
        }

        // 5. Termination is possible if today <= deadline
        return $today->lessThanOrEqualTo($latestPossibleCancellation);
    }

    // =========================================================================
    // Widerrufsfunktion gemäß § 356a BGB
    // =========================================================================

    /**
     * Effektives Vertragsstartdatum (für Widerrufsfrist-Berechnung)
     *
     * Bei verknüpfter Gratis-Mitgliedschaft wird deren Startdatum verwendet,
     * da dies den tatsächlichen Vertragsabschluss darstellt.
     */
    public function getContractStartDateAttribute(): ?string
    {
        // Wenn eine verknüpfte Gratis-Mitgliedschaft existiert, deren Startdatum verwenden
        if ($this->linked_free_membership_id && $this->linkedFreeMembership) {
            return $this->linkedFreeMembership->start_date?->format('Y-m-d');
        }

        // Alternativ: Suche nach einer Gratis-Mitgliedschaft für dieses Mitglied
        $freeMembership = self::where('member_id', $this->member_id)
            ->whereHas('membershipPlan', function ($query) {
                $query->where('is_free_trial_plan', true);
            })
            ->where('start_date', '<=', $this->start_date)
            ->orderBy('start_date', 'asc')
            ->first();

        if ($freeMembership) {
            return $freeMembership->start_date?->format('Y-m-d');
        }

        return $this->start_date?->format('Y-m-d');
    }

    /**
     * Prüft ob ein Widerruf möglich ist (§ 356a BGB)
     *
     * Widerrufsfrist: 14 Tage ab Vertragsabschluss
     */
    public function getWithdrawalEligibleAttribute(): bool
    {
        // Nur bezahlte, aktive/pending Mitgliedschaften können widerrufen werden
        if ($this->is_free_trial) {
            return false;
        }

        // Bereits widerrufen oder gekündigt?
        if ($this->withdrawn_at || $this->status === 'cancelled' || $this->status === 'withdrawn') {
            return false;
        }

        // Nur aktive oder pending Mitgliedschaften
        if (! in_array($this->status, ['active', 'pending'])) {
            return false;
        }

        // 14-Tage-Frist prüfen
        $contractStartDate = $this->contract_start_date;
        if (! $contractStartDate) {
            return false;
        }

        $startDate = Carbon::parse($contractStartDate);
        $deadline = $startDate->copy()->addDays(14)->endOfDay();

        return now()->isBefore($deadline);
    }

    /**
     * Widerrufs-Deadline (Ende der 14-Tage-Frist)
     */
    public function getWithdrawalDeadlineAttribute(): ?string
    {
        if (! $this->withdrawal_eligible) {
            return null;
        }

        $contractStartDate = $this->contract_start_date;
        if (! $contractStartDate) {
            return null;
        }

        $startDate = Carbon::parse($contractStartDate);

        return $startDate->copy()->addDays(14)->endOfDay()->toIso8601String();
    }

    /**
     * Prüft ob die Mitgliedschaft widerrufen wurde
     */
    public function getIsWithdrawnAttribute(): bool
    {
        return $this->status === 'withdrawn' || $this->withdrawn_at !== null;
    }

    /**
     * Scope für widerrufene Mitgliedschaften
     */
    public function scopeWithdrawn($query)
    {
        return $query->where('status', 'withdrawn');
    }

    /**
     * Prüft ob die Erstlaufzeit (commitment_months) bereits abgelaufen ist.
     * Nach dem "Gesetz für faire Verbraucherverträge" (ab 01.03.2022) darf
     * nach der Erstlaufzeit nur noch monatlich verlängert werden.
     */
    public function isInitialTermCompleted(): bool
    {
        if (! $this->start_date || ! $this->membershipPlan) {
            return false;
        }

        $commitmentMonths = $this->membershipPlan->commitment_months ?? 0;
        if ($commitmentMonths === 0) {
            return true; // Kein Commitment = sofort monatlich kündbar
        }

        // end_date is the last included day of a term, so the term that ends on
        // start + commitmentMonths - 1 day completes the initial commitment. Add a
        // day to end_date to compare against the exclusive commitment boundary,
        // otherwise the last day of the initial term is off by one and still
        // counts as "not completed".
        $commitmentEnd = $this->start_date->copy()->addMonths($commitmentMonths);

        return $commitmentEnd->lte(($this->end_date ?? now())->copy()->addDay());
    }
}
