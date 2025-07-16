<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_id',
        'mollie_customer_id',
        'mollie_mandate_id',
        'type',
        'last_four',
        'expiry_date',
        'cardholder_name',
        'bank_name',
        'iban',
        'is_default',
        'status',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'is_default' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function getTypeTextAttribute()
    {
        return [
            'sepa' => 'SEPA-Lastschrift',
            'creditcard' => 'Kreditkarte',
        ][$this->type] ?? $this->type;
    }

    public function getStatusTextAttribute()
    {
        return [
            'active' => 'Aktiv',
            'expired' => 'Abgelaufen',
            'failed' => 'Fehlgeschlagen',
        ][$this->status] ?? $this->status;
    }

    public function getMaskedIbanAttribute()
    {
        if (!$this->iban) {
            return null;
        }

        $length = strlen($this->iban);
        $visible = 4;
        $masked = str_repeat('*', $length - $visible);

        return substr($this->iban, 0, 2) . $masked . substr($this->iban, -$visible);
    }

    public function getIsExpiredAttribute()
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isPast();
    }

    public function getIsSepaAttribute()
    {
        return $this->type === 'sepa';
    }

    public function getIsCreditCardAttribute()
    {
        return $this->type === 'creditcard';
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
