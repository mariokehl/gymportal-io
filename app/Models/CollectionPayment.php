<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment reported for a collection case, together with the way it was
 * distributed across the claims of that case.
 */
class CollectionPayment extends Model
{
    use HasFactory;

    public const MODE_AUTO = 'auto';

    public const MODE_MANUAL = 'manual';

    public const MODE_LABELS = [
        self::MODE_AUTO => 'Automatisch',
        self::MODE_MANUAL => 'Manuell',
    ];

    protected $fillable = [
        'gym_id',
        'collection_case_id',
        'booked_at',
        'amount',
        'allocation_mode',
        'source',
        'allocation',
        'created_by',
        'diagonal_guid',
        'diagonal_state',
    ];

    protected $casts = [
        'booked_at' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'allocation' => 'array',
    ];

    protected $appends = ['allocation_mode_text'];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function collectionCase(): BelongsTo
    {
        return $this->belongsTo(CollectionCase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getAllocationModeTextAttribute(): string
    {
        return self::MODE_LABELS[$this->allocation_mode] ?? 'Unbekannt';
    }
}
