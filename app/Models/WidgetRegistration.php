<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WidgetRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'member_id',
        'membership_plan_id',
        'session_id',
        'ip_address',
        'user_agent',
        'referrer',
        'form_data',
        'status',
        'payment_data',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'form_data' => 'array',
        'payment_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Beziehung zum Fitnessstudio
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Beziehung zum Mitglied
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Beziehung zum Mitgliedschaftsplan
     */
    public function membershipPlan()
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Dauer der Registrierung berechnen
     */
    public function getDurationAttribute()
    {
        if ($this->started_at && $this->completed_at) {
            return $this->completed_at->diffInMinutes($this->started_at);
        }
        return null;
    }

    /**
     * Registrierung als abgeschlossen markieren
     */
    public function markAsCompleted($memberId = null)
    {
        $this->update([
            'status' => 'completed',
            'member_id' => $memberId,
            'completed_at' => now(),
        ]);
    }

    /**
     * Registrierung als fehlgeschlagen markieren
     */
    public function markAsFailed($reason = null)
    {
        $data = $this->payment_data ?? [];
        $data['failure_reason'] = $reason;

        $this->update([
            'status' => 'failed',
            'payment_data' => $data,
        ]);
    }
}
