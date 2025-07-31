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
        // SEPA-Felder
        'sepa_mandate_acknowledged',
        'sepa_mandate_status',
        'sepa_mandate_signed_at',
        'sepa_mandate_reference',
        'sepa_creditor_identifier',
        'sepa_mandate_data',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'sepa_mandate_signed_at' => 'datetime',
        'is_default' => 'boolean',
        'sepa_mandate_acknowledged' => 'boolean',
        'sepa_mandate_data' => 'array',
    ];

    protected $appends = ['type_text', 'status_text', 'sepa_mandate_status_text'];

    /**
     * Standard-Konfiguration für Zahlungsmethoden
     */
    public static $defaultConfig = [
        'banktransfer' => [
            'enabled' => false,
            'name' => 'Manuelle Überweisung (Vorkasse)',
            'description' => 'Mitglied überweist selbst per IBAN',
            'icon' => 'Wallet',
        ],
        'cash' => [
            'enabled' => false,
            'name' => 'Barzahlung',
            'description' => 'Zahlung erfolgt vor Ort in bar',
            'icon' => 'HandCoins',
        ],
        'invoice' => [
            'enabled' => false,
            'name' => 'Zahlung auf Rechnung',
            'description' => 'Mitglied erhält eine Rechnung zur Überweisung',
            'icon' => 'FileText',
        ],
        'standingorder' => [
            'enabled' => false,
            'name' => 'Dauerauftrag',
            'description' => 'Wiederkehrende Überweisung durch Mitglied',
            'icon' => 'DollarSign',
        ],
        'sepa_direct_debit' => [
            'enabled' => false,
            'name' => 'SEPA-Lastschrift',
            'description' => 'Automatischer Einzug vom Bankkonto',
            'icon' => 'CreditCard',
            'requires_mandate' => true,
        ],
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function getTypeTextAttribute()
    {
        return [
            'sepa' => 'SEPA-Lastschrift',
            'sepa_direct_debit' => 'SEPA-Lastschrift',
            'creditcard' => 'Kreditkarte',
            'banktransfer' => 'Banküberweisung',
            'cash' => 'Barzahlung',
            'invoice' => 'Rechnung',
        ][$this->type] ?? $this->type;
    }

    public function getStatusTextAttribute()
    {
        return [
            'active' => 'Aktiv',
            'expired' => 'Abgelaufen',
            'failed' => 'Fehlgeschlagen',
            'pending' => 'Ausstehend',
        ][$this->status] ?? $this->status;
    }

    // SEPA-spezifische Attribute
    public function getSepaMandateStatusTextAttribute(): ?string
    {
        if (!$this->requiresSepaMandate()) {
            return null;
        }

        return match($this->sepa_mandate_status) {
            'pending' => 'Unterschrift ausstehend',
            'signed' => 'Unterschrieben, noch nicht aktiv',
            'active' => 'Aktiv',
            'revoked' => 'Widerrufen',
            'expired' => 'Abgelaufen',
            default => 'Unbekannt'
        };
    }

    public function getSepaMandateStatusColorAttribute(): ?string
    {
        if (!$this->requiresSepaMandate()) {
            return null;
        }

        return match($this->sepa_mandate_status) {
            'pending' => 'yellow',
            'signed' => 'blue',
            'active' => 'green',
            'revoked' => 'red',
            'expired' => 'gray',
            default => 'gray'
        };
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
        return in_array($this->type, ['sepa', 'sepa_direct_debit']);
    }

    public function getIsCreditCardAttribute()
    {
        return $this->type === 'creditcard';
    }

    // SEPA-spezifische Methoden
    public function requiresSepaMandate(): bool
    {
        return $this->is_sepa;
    }

    public function hasActiveSepaMandateRequired(): bool
    {
        return $this->requiresSepaMandate() &&
               in_array($this->sepa_mandate_status, ['signed', 'active']);
    }

    public function isSepaMandateValid(): bool
    {
        return $this->requiresSepaMandate() &&
               in_array($this->sepa_mandate_status, ['signed', 'active']);
    }

    public function markSepaMandateAsAcknowledged(): bool
    {
        if (!$this->requiresSepaMandate() || $this->sepa_mandate_status !== 'pending') {
            return false;
        }

        $this->update([
            'sepa_mandate_acknowledged' => true,
            'sepa_mandate_data' => array_merge($this->sepa_mandate_data ?? [], [
                'acknowledged_at' => now()->toISOString(),
                'acknowledged_online' => true,
            ])
        ]);

        return true;
    }

    public function markSepaMandateAsSigned(): bool
    {
        if (!$this->requiresSepaMandate() || $this->sepa_mandate_status !== 'pending') {
            return false;
        }

        $this->update([
            'sepa_mandate_status' => 'signed',
            'sepa_mandate_signed_at' => now(),
            'sepa_mandate_data' => array_merge($this->sepa_mandate_data ?? [], [
                'signed_at' => now()->toISOString(),
                'signature_method' => 'paper'
            ])
        ]);

        return true;
    }

    public function activateSepaMandate(string $creditorId = null): bool
    {
        if (!$this->requiresSepaMandate() || $this->sepa_mandate_status !== 'signed') {
            return false;
        }

        $updateData = [
            'sepa_mandate_status' => 'active',
            'status' => 'active', // PaymentMethod auch aktiv setzen
            'sepa_mandate_data' => array_merge($this->sepa_mandate_data ?? [], [
                'activated_at' => now()->toISOString(),
            ])
        ];

        if ($creditorId) {
            $updateData['sepa_creditor_identifier'] = $creditorId;
        }

        $this->update($updateData);

        return true;
    }

    public function revokeSepaMandate(string $reason = null): bool
    {
        if (!$this->requiresSepaMandate() || !in_array($this->sepa_mandate_status, ['signed', 'active'])) {
            return false;
        }

        $this->update([
            'sepa_mandate_status' => 'revoked',
            'status' => 'expired', // PaymentMethod deaktivieren
            'sepa_mandate_data' => array_merge($this->sepa_mandate_data ?? [], [
                'revoked_at' => now()->toISOString(),
                'revocation_reason' => $reason
            ])
        ]);

        return true;
    }

    public function generateSepaMandateReference(): string
    {
        $member = $this->member;
        $gym = $member->gym;
        $gymPrefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $gym->name), 0, 3));
        $timestamp = now()->format('ymd');
        $memberNumber = str_pad($member->id, 4, '0', STR_PAD_LEFT);
        $random = strtoupper(substr(uniqid(), -2));

        return "{$gymPrefix}{$timestamp}{$memberNumber}{$random}";
    }

    // Static Methods
    public static function createSepaPaymentMethod(Member $member, bool $acknowledgedOnline = false): self
    {
        $paymentMethod = self::create([
            'member_id' => $member->id,
            'type' => 'sepa_direct_debit',
            'status' => 'pending',
            'is_default' => true,
            'sepa_mandate_acknowledged' => $acknowledgedOnline,
            'sepa_mandate_status' => 'pending',
            'sepa_mandate_data' => [
                'created_via' => 'widget_registration',
                'acknowledged_online' => $acknowledgedOnline,
                'created_at' => now()->toISOString(),
            ]
        ]);

        // Generiere Mandatsreferenz
        $paymentMethod->update([
            'sepa_mandate_reference' => $paymentMethod->generateSepaMandateReference()
        ]);

        return $paymentMethod;
    }

    /**
     * Hole die Standard-Konfiguration für alle Zahlungsmethoden
     */
    public static function getDefaultConfig(): array
    {
        return self::$defaultConfig;
    }

    /**
     * Hole die Konfiguration für eine spezifische Zahlungsmethode
     */
    public static function getConfigForType(string $type): ?array
    {
        return self::$defaultConfig[$type] ?? null;
    }

    /**
     * Prüfe ob eine Zahlungsmethode ein Mandat benötigt
     */
    public static function typeRequiresMandate(string $type): bool
    {
        $config = self::getConfigForType($type);
        return $config['requires_mandate'] ?? false;
    }

    // Scopes
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSepa($query)
    {
        return $query->whereIn('type', ['sepa', 'sepa_direct_debit']);
    }

    public function scopeWithPendingSepaMandate($query)
    {
        return $query->sepa()->where('sepa_mandate_status', 'pending');
    }

    public function scopeWithActiveSepaMandate($query)
    {
        return $query->sepa()->where('sepa_mandate_status', 'active');
    }

    public function scopeRequiringSepaSignature($query)
    {
        return $query->sepa()
                    ->where('sepa_mandate_status', 'pending')
                    ->where('sepa_mandate_acknowledged', true);
    }
}
