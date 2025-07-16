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
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'pause_start_date' => 'date',
        'pause_end_date' => 'date',
        'cancellation_date' => 'date',
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
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'active' => 'green',
            'paused' => 'yellow',
            'cancelled' => 'red',
            'expired' => 'gray',
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
}
