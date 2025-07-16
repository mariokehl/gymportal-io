<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'member_id',
        'invoice_number',
        'amount',
        'currency',
        'description',
        'status',
        'invoice_date',
        'due_date',
        'paid_at',
        'line_items',
        'tax_amount',
        'tax_rate',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'timestamp',
        'line_items' => 'array',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getStatusTextAttribute()
    {
        return [
            'draft' => 'Entwurf',
            'sent' => 'Versendet',
            'paid' => 'Bezahlt',
            'overdue' => 'Überfällig',
            'canceled' => 'Storniert',
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'draft' => 'gray',
            'sent' => 'blue',
            'paid' => 'green',
            'overdue' => 'red',
            'canceled' => 'gray',
        ][$this->status] ?? 'gray';
    }

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2, ',', '.') . ' €';
    }

    public function getFormattedTaxAmountAttribute()
    {
        return number_format($this->tax_amount, 2, ',', '.') . ' €';
    }

    public function getNetAmountAttribute()
    {
        return $this->amount - $this->tax_amount;
    }

    public function getFormattedNetAmountAttribute()
    {
        return number_format($this->net_amount, 2, ',', '.') . ' €';
    }

    public function getTotalPaymentsAttribute()
    {
        return $this->payments()->where('status', 'paid')->sum('amount');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->amount - $this->total_payments;
    }

    public function getFormattedRemainingAmountAttribute()
    {
        return number_format($this->remaining_amount, 2, ',', '.') . ' €';
    }

    public function getIsOverdueAttribute()
    {
        return $this->status === 'sent' && $this->due_date < now();
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->remaining_amount <= 0;
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'sent')
            ->where('due_date', '<', now());
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (!$invoice->invoice_number) {
                $invoice->invoice_number = static::generateInvoiceNumber($invoice->gym_id);
            }
        });
    }

    public static function generateInvoiceNumber($gymId)
    {
        $year = now()->year;
        $lastInvoice = static::where('gym_id', $gymId)
            ->where('invoice_number', 'like', "RE-{$year}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "RE-{$year}-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
