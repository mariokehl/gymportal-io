<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'type',
        'name',
        'description',
        'price',
        'value',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'value' => 'integer',
        'sort_order' => 'integer',
        'active' => 'boolean',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'solarium_minutes' => 'Solarium',
            'visit_card' => '10er-Karte',
            'day_pass' => 'Tageskarte',
            default => $this->type,
        };
    }

    public function getValueLabelAttribute(): string
    {
        return match ($this->type) {
            'solarium_minutes' => $this->value . ' Minuten',
            'visit_card' => $this->value . ' Eintritte',
            'day_pass' => 'Tagespass',
            default => (string) $this->value,
        };
    }

    public function getServiceDescriptionAttribute(): string
    {
        return "{$this->name} ({$this->type_label}, {$this->value_label})";
    }
}
