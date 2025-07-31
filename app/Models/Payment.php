<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'membership_id',
        'mollie_payment_id',
        'amount',
        'currency',
        'description',
        'status',
        'mollie_status',
        'checkout_url',
        'user_id',
        'member_id',
        'invoice_id',
        'metadata',
        'failed_at',
        'canceled_at',
        'expired_at',
        'webhook_processed_at',
        'due_date',
        'paid_date',
        'payment_method',
        'transaction_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_date' => 'date',
        'metadata' => 'array',
    ];

    protected $appends = ['status_text', 'status_color', 'payment_method_text'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function getStatusTextAttribute()
    {
        return [
            'pending' => 'Ausstehend',
            'paid' => 'Bezahlt',
            'failed' => 'Fehlgeschlagen',
            'refunded' => 'Erstattet',
            'expired' => 'Abgelaufen',
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'pending' => 'yellow',
            'paid' => 'green',
            'failed' => 'red',
            'refunded' => 'blue',
            'expired' => 'gray',
        ][$this->status] ?? 'gray';
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, ',', '.') . ' €';
    }

    public function getPaymentMethodTextAttribute()
    {
        return [
            'sepa' => 'SEPA-Lastschrift',
            'sepa_direct_debit' => 'SEPA-Lastschrift',
            'creditcard' => 'Kreditkarte',
            'banktransfer' => 'Banküberweisung',
            'cash' => 'Barzahlung',
            'invoice' => 'Rechnung',
        ][$this->payment_method] ?? $this->payment_method;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '<', now());
    }
}
