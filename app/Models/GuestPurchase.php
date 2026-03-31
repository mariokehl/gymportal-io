<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'guest_product_id',
        'payment_id',
        'status',
        'credits_remaining',
        'valid_until',
        'activated_at',
    ];

    protected $casts = [
        'credits_remaining' => 'integer',
        'valid_until' => 'datetime',
        'activated_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(GuestProduct::class, 'guest_product_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Check if this purchase is currently active and usable
     */
    public function isActive(): bool
    {
        if ($this->status !== 'paid') {
            return false;
        }

        // For credit-based products (solarium, visit card)
        if ($this->credits_remaining !== null && $this->credits_remaining <= 0) {
            return false;
        }

        // For time-limited products (day pass)
        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Consume credits from this purchase
     */
    public function consume(int $amount = 1): bool
    {
        if (!$this->isActive() || $this->credits_remaining === null) {
            return false;
        }

        if ($this->credits_remaining < $amount) {
            return false;
        }

        $this->decrement('credits_remaining', $amount);

        if ($this->credits_remaining <= 0) {
            $this->update(['status' => 'consumed']);
        }

        return true;
    }

    /**
     * Activate this purchase after successful payment
     */
    public function activate(): void
    {
        $product = $this->product;

        $this->status = 'paid';
        $this->activated_at = now();
        $this->credits_remaining = $product->value;

        if ($product->type === 'day_pass') {
            $this->valid_until = now()->endOfDay();
        }

        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'paid')
            ->where(function ($q) {
                $q->whereNull('credits_remaining')
                    ->orWhere('credits_remaining', '>', 0);
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            });
    }

    public function scopeForMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }
}
