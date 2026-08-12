<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single dunning step that was reached for a member.
 *
 * Levels 1-3 are the regular dunning escalation, level 4 marks the handover to
 * the collection partner (see {@see CollectionCase}).
 */
class DunningNotice extends Model
{
    use HasFactory;

    public const LEVEL_REMINDER = 1;

    public const LEVEL_FIRST_NOTICE = 2;

    public const LEVEL_SECOND_NOTICE = 3;

    public const LEVEL_COLLECTION = 4;

    public const LEVEL_LABELS = [
        self::LEVEL_REMINDER => 'Zahlungserinnerung',
        self::LEVEL_FIRST_NOTICE => '1. Mahnung',
        self::LEVEL_SECOND_NOTICE => '2. Mahnung',
        self::LEVEL_COLLECTION => 'Inkasso',
    ];

    protected $fillable = [
        'gym_id',
        'member_id',
        'payment_id',
        'level',
        'fee',
        'triggered_at',
        'sent_at',
        'channel',
        'meta',
    ];

    protected $casts = [
        'level' => 'integer',
        'fee' => 'decimal:2',
        'triggered_at' => 'datetime',
        'sent_at' => 'datetime',
        'meta' => 'array',
    ];

    protected $appends = ['level_label'];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVEL_LABELS[$this->level] ?? 'Unbekannt';
    }
}
