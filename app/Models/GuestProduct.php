<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product a guest can buy in the guest app: solarium minutes, a visit card
 * or a day pass. What it grants is booked onto the guest's access config.
 */
class GuestProduct extends Model
{
    use HasFactory;

    public const TYPE_SOLARIUM_MINUTES = 'solarium_minutes';

    public const TYPE_VISIT_CARD = 'visit_card';

    public const TYPE_DAY_PASS = 'day_pass';

    public const TYPES = [
        self::TYPE_SOLARIUM_MINUTES,
        self::TYPE_VISIT_CARD,
        self::TYPE_DAY_PASS,
    ];

    protected $fillable = [
        'gym_id',
        'type',
        'name',
        'description',
        'price',
        'quantity',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    protected $appends = [
        'type_label',
        'quantity_label',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SOLARIUM_MINUTES => 'Solarium-Minuten',
            self::TYPE_VISIT_CARD => 'Mehrfachkarte',
            self::TYPE_DAY_PASS => 'Tageskarte',
            default => (string) $this->type,
        };
    }

    /**
     * What one purchase grants, e.g. "20 Minuten" or "10 Eintritte".
     */
    public function getQuantityLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_SOLARIUM_MINUTES => $this->quantity.' Minuten',
            self::TYPE_VISIT_CARD => $this->quantity.' Eintritte',
            self::TYPE_DAY_PASS => '1 Tag',
            default => (string) $this->quantity,
        };
    }

    public function getServiceDescriptionAttribute(): string
    {
        return "{$this->name} ({$this->quantity_label})";
    }
}
