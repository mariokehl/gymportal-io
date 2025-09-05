<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'action',
        'service',
        'method',
        'success',
        'performed_by',
        'device_id',
        'ip_address',
        'user_agent',
        'metadata',
        'accessed_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'metadata' => 'array',
        'accessed_at' => 'datetime',
    ];

    /**
     * Action types
     */
    const ACTION_ACCESS_ATTEMPT = 'access_attempt';
    const ACTION_CONFIG_UPDATED = 'config_updated';
    const ACTION_QR_INVALIDATED = 'qr_invalidated';
    const ACTION_APP_LINK_SENT = 'app_link_sent';
    const ACTION_CREDIT_CONSUMED = 'credit_consumed';
    const ACTION_CREDIT_ADDED = 'credit_added';
    const ACTION_NFC_REGISTERED = 'nfc_registered';
    const ACTION_NFC_REMOVED = 'nfc_removed';

    /**
     * Service types
     */
    const SERVICE_GYM = 'gym';
    const SERVICE_SOLARIUM = 'solarium';
    const SERVICE_VENDING = 'vending';
    const SERVICE_MASSAGE = 'massage';
    const SERVICE_COFFEE = 'coffee';

    /**
     * Access methods
     */
    const METHOD_QR = 'qr';
    const METHOD_NFC = 'nfc';
    const METHOD_MANUAL = 'manual';

    /**
     * Get the member that owns the log entry
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the user who performed the action
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Get human-readable action name
     */
    public function getActionNameAttribute(): string
    {
        $actions = [
            self::ACTION_ACCESS_ATTEMPT => 'Zugangsversuch',
            self::ACTION_CONFIG_UPDATED => 'Konfiguration aktualisiert',
            self::ACTION_QR_INVALIDATED => 'QR-Code invalidiert',
            self::ACTION_APP_LINK_SENT => 'App-Link versendet',
            self::ACTION_CREDIT_CONSUMED => 'Guthaben verbraucht',
            self::ACTION_CREDIT_ADDED => 'Guthaben hinzugefügt',
            self::ACTION_NFC_REGISTERED => 'NFC-Tag registriert',
            self::ACTION_NFC_REMOVED => 'NFC-Tag entfernt',
        ];

        return $actions[$this->action] ?? $this->action;
    }

    /**
     * Get human-readable service name
     */
    public function getServiceNameAttribute(): string
    {
        $services = [
            self::SERVICE_GYM => 'Fitnessstudio',
            self::SERVICE_SOLARIUM => 'Solarium',
            self::SERVICE_VENDING => 'Vending Machine',
            self::SERVICE_MASSAGE => 'Massagestuhl',
            self::SERVICE_COFFEE => 'Kaffee-Flatrate',
        ];

        return $services[$this->service] ?? $this->service ?? 'System';
    }

    /**
     * Get human-readable method name
     */
    public function getMethodNameAttribute(): string
    {
        $methods = [
            self::METHOD_QR => 'QR-Code',
            self::METHOD_NFC => 'NFC-Tag',
            self::METHOD_MANUAL => 'Manuell',
        ];

        return $methods[$this->method] ?? $this->method ?? '-';
    }

    /**
     * Get formatted timestamp for display
     */
    public function getFormattedTimeAttribute(): string
    {
        $timestamp = $this->accessed_at ?? $this->created_at;
        return $timestamp->format('d.m.Y H:i:s');
    }

    /**
     * Scope: Successful access attempts
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope: Failed access attempts
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope: Access attempts only
     */
    public function scopeAccessAttempts($query)
    {
        return $query->where('action', self::ACTION_ACCESS_ATTEMPT);
    }

    /**
     * Scope: By service
     */
    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Scope: By method
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', $method);
    }

    /**
     * Scope: Recent logs (last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }

    /**
     * Scope: By device
     */
    public function scopeByDevice($query, string $deviceId)
    {
        return $query->where('device_id', $deviceId);
    }

    /**
     * Get statistics for a member
     */
    public static function getMemberStatistics(int $memberId, ?string $period = '30d'): array
    {
        $query = self::where('member_id', $memberId);

        // Apply period filter
        switch ($period) {
            case '7d':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case '30d':
                $query->where('created_at', '>=', now()->subDays(30));
                break;
            case '3m':
                $query->where('created_at', '>=', now()->subMonths(3));
                break;
            case '1y':
                $query->where('created_at', '>=', now()->subYear());
                break;
        }

        $accessAttempts = clone $query;
        $totalAttempts = $accessAttempts->accessAttempts()->count();
        $successfulAttempts = $accessAttempts->accessAttempts()->successful()->count();

        return [
            'total_attempts' => $totalAttempts,
            'successful_attempts' => $successfulAttempts,
            'failed_attempts' => $totalAttempts - $successfulAttempts,
            'success_rate' => $totalAttempts > 0 ? round(($successfulAttempts / $totalAttempts) * 100, 2) : 0,
            'services_used' => $query->whereNotNull('service')->distinct('service')->count('service'),
            'last_access' => $query->accessAttempts()->successful()->latest()->first()?->created_at,
        ];
    }
}
