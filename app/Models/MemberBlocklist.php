<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberBlocklist extends Model
{
    protected $table = 'member_blocklist';

    protected $fillable = [
        'gym_id',
        'original_member_id',
        'blocked_by',
        'reason',
        'notes',
        'hash_iban',
        'hash_phone',
        'hash_address',
        'encrypted_last_name',
        'encrypted_first_name',
        'encrypted_birthdate',
        'blocked_at',
        'blocked_until',
    ];

    protected $casts = [
        'blocked_at' => 'datetime',
        'blocked_until' => 'datetime',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'original_member_id');
    }

    public function blockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * Scope: nur aktive (nicht abgelaufene) Sperren.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('blocked_until')
              ->orWhere('blocked_until', '>', now());
        });
    }

    /**
     * Prüfe ob die Sperre noch aktiv ist.
     */
    public function isActive(): bool
    {
        return $this->blocked_until === null || $this->blocked_until->isFuture();
    }
}
