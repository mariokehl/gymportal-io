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
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'pause_start_date' => 'date',
        'pause_end_date' => 'date',
        'cancellation_date' => 'date',
    ];

    protected $appends = [
        'min_cancellation_date',
        'default_cancellation_date',
        'can_cancel',
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

    public function getStatusTextAttribute()
    {
        return [
            'active' => 'Aktiv',
            'paused' => 'Pausiert',
            'cancelled' => 'Gekündigt',
            'expired' => 'Abgelaufen',
            'pending' => 'Ausstehend', // Neu hinzugefügt
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'active' => 'green',
            'paused' => 'yellow',
            'cancelled' => 'red',
            'expired' => 'gray',
            'pending' => 'orange', // Neu hinzugefügt
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

        // Subtract cancellation period days (user must cancel before commitment ends)
        if ($this->membershipPlan->cancellation_period_days) {
            $date->subDays($this->membershipPlan->cancellation_period_days);
        }

        return $date->format('Y-m-d');
    }

    public function getDefaultCancellationDateAttribute()
    {
        // If membership is already cancelled, return the existing cancellation_date
        if ($this->status === 'cancelled' && $this->cancellation_date) {
            return $this->cancellation_date->format('Y-m-d');
        }

        // Always use end_date if available
        if ($this->end_date) {
            return $this->end_date->format('Y-m-d');
        }

        // Fallback to min_cancellation_date if no end_date exists
        return $this->min_cancellation_date ? $this->min_cancellation_date->format('Y-m-d') : null;
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
        $renewalMonths = $this->membershipPlan->renewal_months ?? 1;
        $cancellationPeriodDays = $this->membershipPlan->cancellation_period_days ?? 0;

        // 1. End of minimum term
        $commitmentEnd = $start->copy()->addMonths($commitmentMonths);

        // If no cancellation is possible during the minimum contract period
        if ($today->lessThan($commitmentEnd)) {
            return false;
        }

        // 2. We are after the minimum term → extension periods apply
        $periodStart = $commitmentEnd->copy();

        // Find current or next renewal period
        while ($periodStart->lessThanOrEqualTo($today)) {
            $periodStart->addMonths($renewalMonths);
        }

        $periodEnd = $periodStart->copy();

        // 3. Calculate the latest possible termination date for this period.
        $latestPossibleCancellation = $periodEnd->copy()->subDays($cancellationPeriodDays);

        // 4. Termination is possible if today <= deadline
        return $today->lessThanOrEqualTo($latestPossibleCancellation);
    }
}
