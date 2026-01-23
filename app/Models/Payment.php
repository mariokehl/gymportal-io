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
        'execution_date',
        'due_date',
        'paid_date',
        'payment_method',
        'transaction_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'execution_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'canceled_at' => 'datetime',
        'failed_at' => 'datetime',
        'expired_at' => 'datetime',
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

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Get all refunds for this payment.
     */
    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Get all chargebacks for this payment.
     */
    public function chargebacks()
    {
        return $this->hasMany(Chargeback::class);
    }

    /**
     * Get the original payment (for refunds/chargebacks linked via transaction_id).
     */
    public function originalPayment()
    {
        return $this->belongsTo(Payment::class, 'transaction_id', 'id');
    }

    /**
     * Get related payments (refunds/chargebacks) that reference this payment via transaction_id.
     */
    public function relatedPayments()
    {
        return $this->hasMany(Payment::class, 'transaction_id', 'id');
    }

    public function getStatusTextAttribute()
    {
        return [
            'pending' => 'Ausstehend',
            'paid' => 'Bezahlt',
            'failed' => 'Fehlgeschlagen',
            'refunded' => 'Erstattet',
            'partially_refunded' => 'Teilweise erstattet',
            'chargeback' => 'Rückbuchung',
            'expired' => 'Verfallen',
            'canceled' => 'Abgebrochen',
            'completed' => 'Bezahlt',
            'unknown' => 'Unbekannt',
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'pending' => 'yellow',
            'paid' => 'green',
            'failed' => 'red',
            'refunded' => 'blue',
            'partially_refunded' => 'blue',
            'chargeback' => 'red',
            'expired' => 'gray',
            'canceled' => 'red',
            'completed' => 'green'
        ][$this->status] ?? 'gray';
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, ',', '.') . ' €';
    }

    public function getPaymentMethodTextAttribute()
    {
        return [
            'sepa_direct_debit' => 'SEPA-Lastschrift',
            'creditcard' => 'Kreditkarte',
            'banktransfer' => 'Banküberweisung',
            'cash' => 'Barzahlung',
            'invoice' => 'Rechnung',
            'mollie_creditcard' => 'Mollie: Kreditkarte',
            'mollie_directdebit' => 'Mollie: SEPA-Lastschrift',
            'mollie_paypal' => 'Mollie: PayPal',
            'mollie_klarna' => 'Mollie: Klarna',
            'mollie_banktransfer' => 'Mollie: SEPA-Überweisung',
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

    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '<', now());
    }

    /**
     * Check if payment can be canceled
     */
    public function canBeCanceled(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment can be marked as paid
     */
    public function canBeMarkedAsPaid(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->status === 'paid';
    }
}
