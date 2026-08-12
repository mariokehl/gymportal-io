<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A batch handover of overdue members to the collection partner.
 */
class CollectionRun extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_HANDED_OVER = 'handed_over';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Entwurf',
        self::STATUS_HANDED_OVER => 'Übergeben',
        self::STATUS_IN_PROGRESS => 'In Bearbeitung',
        self::STATUS_COMPLETED => 'Abgeschlossen',
        self::STATUS_CANCELLED => 'Abgebrochen',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_HANDED_OVER => 'indigo',
        self::STATUS_IN_PROGRESS => 'orange',
        self::STATUS_COMPLETED => 'green',
        self::STATUS_CANCELLED => 'gray',
    ];

    protected $fillable = [
        'gym_id',
        'run_number',
        'partner',
        'handed_over_at',
        'status',
        'member_count',
        'principal_amount',
        'dunning_amount',
        'flat_amount',
        'total_amount',
        'created_by',
    ];

    protected $casts = [
        'handed_over_at' => 'datetime',
        'member_count' => 'integer',
        'principal_amount' => 'decimal:2',
        'dunning_amount' => 'decimal:2',
        'flat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected $appends = ['status_text', 'status_color'];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CollectionCase::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusTextAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Unbekannt';
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    /**
     * Generate the next run number for a gym, e.g. IL-2026-005.
     *
     * Mirrors the numbering approach used by {@see Invoice::generateInvoiceNumber()}.
     */
    public static function generateRunNumber(int $gymId): string
    {
        $year = now()->year;
        $prefix = "IL-{$year}-";

        $last = static::where('gym_id', $gymId)
            ->where('run_number', 'like', $prefix.'%')
            ->orderByDesc('run_number')
            ->first();

        $next = $last ? ((int) substr($last->run_number, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
