<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function purchases(): HasMany
    {
        return $this->hasMany(GuestPurchase::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
