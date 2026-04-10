<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingSolariumRedemption extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Nach wie vielen Sekunden ohne Acknowledgement eine Redemption als expired gilt.
     */
    public const EXPIRY_SECONDS = 60;

    protected $fillable = [
        'member_id',
        'gym_id',
        'minutes',
        'status',
        'failure_reason',
        'acknowledged_by_scanner_id',
        'acknowledged_at',
    ];

    protected $casts = [
        'minutes' => 'integer',
        'acknowledged_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(GymScanner::class, 'acknowledged_by_scanner_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->isPending()
            && $this->created_at->lt(now()->subSeconds(self::EXPIRY_SECONDS));
    }
}
