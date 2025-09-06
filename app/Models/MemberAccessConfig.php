<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberAccessConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'qr_code_enabled',
        'qr_code_invalidated_at',
        'qr_code_invalidated_by',
        'nfc_enabled',
        'nfc_uid',
        'nfc_registered_at',
        'solarium_enabled',
        'solarium_minutes',
        'vending_enabled',
        'vending_credit',
        'massage_enabled',
        'massage_sessions',
        'coffee_flat_enabled',
        'coffee_flat_expiry',
        'additional_services',
    ];

    protected $casts = [
        'qr_code_enabled' => 'boolean',
        'qr_code_invalidated_at' => 'datetime',
        'nfc_enabled' => 'boolean',
        'nfc_registered_at' => 'datetime',
        'solarium_enabled' => 'boolean',
        'solarium_minutes' => 'integer',
        'vending_enabled' => 'boolean',
        'vending_credit' => 'decimal:2',
        'massage_enabled' => 'boolean',
        'massage_sessions' => 'integer',
        'coffee_flat_enabled' => 'boolean',
        'coffee_flat_expiry' => 'date',
        'additional_services' => 'array',
    ];

    protected $appends = [
        'has_active_services',
        'active_services_count',
    ];

    /**
     * Get the member that owns the access configuration
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the user who invalidated the QR code
     */
    public function qrInvalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qr_code_invalidated_by');
    }

    /**
     * Get access logs for this configuration
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(MemberAccessLog::class, 'member_id', 'member_id');
    }

    /**
     * Check if any additional services are enabled
     */
    public function getHasActiveServicesAttribute(): bool
    {
        return $this->solarium_enabled ||
               $this->vending_enabled ||
               $this->massage_enabled ||
               $this->coffee_flat_enabled;
    }

    /**
     * Count active services
     */
    public function getActiveServicesCountAttribute(): int
    {
        $count = 0;

        if ($this->qr_code_enabled) $count++;
        if ($this->nfc_enabled) $count++;
        if ($this->solarium_enabled) $count++;
        if ($this->vending_enabled) $count++;
        if ($this->massage_enabled) $count++;
        if ($this->coffee_flat_enabled) $count++;

        return $count;
    }

    /**
     * Check if NFC is properly configured
     */
    public function hasValidNfc(): bool
    {
        return $this->nfc_enabled && !empty($this->nfc_uid);
    }

    /**
     * Check if QR code is valid
     */
    public function hasValidQrCode(): bool
    {
        return $this->qr_code_enabled && !$this->qr_code_invalidated_at;
    }

    /**
     * Check if coffee flat is still valid
     */
    public function isCoffeeFlatValid(): bool
    {
        if (!$this->coffee_flat_enabled) {
            return false;
        }

        if (!$this->coffee_flat_expiry) {
            return true; // No expiry means unlimited
        }

        return $this->coffee_flat_expiry->isFuture();
    }

    /**
     * Format NFC UID for display
     */
    public function getFormattedNfcUidAttribute(): ?string
    {
        if (!$this->nfc_uid) {
            return null;
        }

        // Add colons every 2 characters for better readability
        return implode(':', str_split($this->nfc_uid, 2));
    }

    /**
     * Check if member can access a specific service
     */
    public function canAccessService(string $service): bool
    {
        switch ($service) {
            case 'gym':
                return $this->qr_code_enabled || $this->nfc_enabled;

            case 'solarium':
                return $this->solarium_enabled && $this->solarium_minutes > 0;

            case 'vending':
                return $this->vending_enabled && $this->vending_credit > 0;

            case 'massage':
                return $this->massage_enabled && $this->massage_sessions > 0;

            case 'coffee':
                return $this->isCoffeeFlatValid();

            default:
                return false;
        }
    }

    /**
     * Consume service credits
     */
    public function consumeService(string $service, float $amount = 1): bool
    {
        switch ($service) {
            case 'solarium':
                if ($this->solarium_minutes >= $amount) {
                    $this->decrement('solarium_minutes', $amount);
                    return true;
                }
                break;

            case 'vending':
                if ($this->vending_credit >= $amount) {
                    $this->decrement('vending_credit', $amount);
                    return true;
                }
                break;

            case 'massage':
                if ($this->massage_sessions >= $amount) {
                    $this->decrement('massage_sessions', $amount);
                    return true;
                }
                break;
        }

        return false;
    }

    /**
     * Add service credits
     */
    public function addCredit(string $service, float $amount): void
    {
        switch ($service) {
            case 'solarium':
                $this->increment('solarium_minutes', $amount);
                break;

            case 'vending':
                $this->increment('vending_credit', $amount);
                break;

            case 'massage':
                $this->increment('massage_sessions', $amount);
                break;
        }
    }

    /**
     * Scope: Members with NFC enabled
     */
    public function scopeWithNfcEnabled($query)
    {
        return $query->where('nfc_enabled', true)->whereNotNull('nfc_uid');
    }

    /**
     * Scope: Members with QR enabled
     */
    public function scopeWithQrEnabled($query)
    {
        return $query->where('qr_code_enabled', true);
    }

    /**
     * Scope: Members with specific service enabled
     */
    public function scopeWithServiceEnabled($query, string $service)
    {
        switch ($service) {
            case 'solarium':
                return $query->where('solarium_enabled', true);
            case 'vending':
                return $query->where('vending_enabled', true);
            case 'massage':
                return $query->where('massage_enabled', true);
            case 'coffee':
                return $query->where('coffee_flat_enabled', true);
            default:
                return $query;
        }
    }
}
