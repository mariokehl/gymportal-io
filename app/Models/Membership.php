<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'notes',
        'linked_free_membership_id',
        // Widerrufs-Felder (§ 356a BGB)
        'withdrawn_at',
        'withdrawal_confirmation_sent_to',
        'withdrawal_refund_amount',
        'withdrawal_refund_processed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'pause_start_date' => 'date',
        'pause_end_date' => 'date',
        'cancellation_date' => 'date',
        // Widerrufs-Felder (§ 356a BGB)
        'withdrawn_at' => 'datetime',
        'withdrawal_refund_processed_at' => 'datetime',
        'withdrawal_refund_amount' => 'decimal:2',
    ];

    protected $appends = [
        'min_cancellation_date',
        'default_cancellation_date',
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
        if (!$this->end_date) {
            return null;
        }

        return $this->start_date->diffInMonths($this->end_date);
    }

    public function getIsPausedAttribute()
    {
        return $this->status === 'paused';
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

        return $this->update(['status' => 'active']);
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function getMinCancellationDateAttribute()
    {
        if (!$this->start_date || !$this->membershipPlan) {
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

    public function getCanCancelAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    public function canBeCancelled(): bool
    {
        if (!$this->start_date || !$this->membershipPlan) {
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
        if (!in_array($this->status, ['active', 'pending'])) {
            return false;
        }

        // 14-Tage-Frist prüfen
        $contractStartDate = $this->contract_start_date;
        if (!$contractStartDate) {
            return false;
        }

        $startDate = \Carbon\Carbon::parse($contractStartDate);
        $deadline = $startDate->copy()->addDays(14)->endOfDay();

        return now()->isBefore($deadline);
    }

    /**
     * Widerrufs-Deadline (Ende der 14-Tage-Frist)
     */
    public function getWithdrawalDeadlineAttribute(): ?string
    {
        if (!$this->withdrawal_eligible) {
            return null;
        }

        $contractStartDate = $this->contract_start_date;
        if (!$contractStartDate) {
            return null;
        }

        $startDate = \Carbon\Carbon::parse($contractStartDate);
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
        if (!$this->start_date || !$this->membershipPlan) {
            return false;
        }

        $commitmentMonths = $this->membershipPlan->commitment_months ?? 0;
        if ($commitmentMonths === 0) {
            return true; // Kein Commitment = sofort monatlich kündbar
        }

        $commitmentEnd = $this->start_date->copy()->addMonths($commitmentMonths);
        return $commitmentEnd->lte($this->end_date ?? now());
    }
}
