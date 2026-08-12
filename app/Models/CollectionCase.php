<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One member's debt collection case with the partner.
 */
class CollectionCase extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PARTIAL_PAYMENT = 'partial_payment';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_IN_PROGRESS => 'In Bearbeitung',
        self::STATUS_PARTIAL_PAYMENT => 'Teilzahlung',
        self::STATUS_COMPLETED => 'Abgeschlossen',
        self::STATUS_CANCELLED => 'Storniert',
        self::STATUS_REJECTED => 'Abgelehnt',
    ];

    public const STATUS_COLORS = [
        self::STATUS_IN_PROGRESS => 'orange',
        self::STATUS_PARTIAL_PAYMENT => 'yellow',
        self::STATUS_COMPLETED => 'green',
        self::STATUS_CANCELLED => 'gray',
        self::STATUS_REJECTED => 'red',
    ];

    /**
     * Statuses in which the case is still actively being worked on by the partner.
     */
    public const OPEN_STATUSES = [
        self::STATUS_IN_PROGRESS,
        self::STATUS_PARTIAL_PAYMENT,
    ];

    protected $fillable = [
        'gym_id',
        'collection_run_id',
        'member_id',
        'case_number',
        'partner_reference',
        'status',
        'handed_over_at',
        'closed_at',
        'rejection_reason',
        'principal_amount',
        'dunning_amount',
        'flat_amount',
        'paid_amount',
        'notes',
        'diagonal_guid',
        'diagonal_state',
        'diagonal_synced_at',
    ];

    protected $casts = [
        'handed_over_at' => 'datetime',
        'closed_at' => 'datetime',
        'diagonal_synced_at' => 'datetime',
        'principal_amount' => 'decimal:2',
        'dunning_amount' => 'decimal:2',
        'flat_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    protected $appends = ['status_text', 'status_color', 'total_amount', 'open_amount', 'is_open'];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CollectionRun::class, 'collection_run_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(CollectionClaim::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CollectionPayment::class);
    }

    public function getStatusTextAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? 'Unbekannt';
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getTotalAmountAttribute(): string
    {
        return number_format(
            (float) $this->principal_amount + (float) $this->dunning_amount + (float) $this->flat_amount,
            2,
            '.',
            ''
        );
    }

    public function getOpenAmountAttribute(): string
    {
        return number_format(
            max(0, (float) $this->total_amount - (float) $this->paid_amount),
            2,
            '.',
            ''
        );
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Generate the next case number for a gym, e.g. CASE-2026-0142.
     */
    public static function generateCaseNumber(int $gymId): string
    {
        $year = now()->year;
        $prefix = "CASE-{$year}-";

        $last = static::where('gym_id', $gymId)
            ->where('case_number', 'like', $prefix.'%')
            ->orderByDesc('case_number')
            ->first();

        $next = $last ? ((int) substr($last->case_number, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
