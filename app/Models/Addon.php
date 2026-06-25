<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Addon extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gym_id',
        'name',
        'description',
        'price',
        'payment_method',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = ['formatted_price'];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function membershipPlans(): BelongsToMany
    {
        return $this->belongsToMany(MembershipPlan::class)
            ->withPivot('mode')
            ->withTimestamps();
    }

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class)
            ->withPivot('mode', 'price', 'payment_id', 'completed_at', 'completed_by')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2, ',', '.').' €';
    }
}
