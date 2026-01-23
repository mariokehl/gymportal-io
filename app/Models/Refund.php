<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'mollie_refund_id',
        'amount',
        'currency',
        'description',
        'status',
        'mollie_status',
        'created_by',
        'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $appends = ['status_text', 'status_color'];

    /**
     * Get the original payment this refund belongs to.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the user who created this refund.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusTextAttribute()
    {
        return [
            'pending' => 'Ausstehend',
            'processing' => 'In Bearbeitung',
            'refunded' => 'Erstattet',
            'failed' => 'Fehlgeschlagen',
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'pending' => 'yellow',
            'processing' => 'blue',
            'refunded' => 'green',
            'failed' => 'red',
        ][$this->status] ?? 'gray';
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, ',', '.') . ' €';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
