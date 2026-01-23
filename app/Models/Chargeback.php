<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chargeback extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'mollie_chargeback_id',
        'amount',
        'currency',
        'status',
        'mollie_status',
        'reason',
        'chargeback_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'chargeback_date' => 'datetime',
    ];

    protected $appends = ['status_text', 'status_color'];

    /**
     * Get the original payment this chargeback belongs to.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function getStatusTextAttribute()
    {
        return [
            'received' => 'Eingegangen',
            'accepted' => 'Akzeptiert',
            'disputed' => 'Angefochten',
            'reversed' => 'Rückgängig gemacht',
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'received' => 'red',
            'accepted' => 'gray',
            'disputed' => 'yellow',
            'reversed' => 'green',
        ][$this->status] ?? 'gray';
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, ',', '.') . ' €';
    }

    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeDisputed($query)
    {
        return $query->where('status', 'disputed');
    }
}
