<?php

namespace App\Models;

use App\Events\ScannerAccessEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ScannerAccessLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'scanner_access_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'gym_id',
        'device_number',
        'member_id',
        'scan_type',
        'access_granted',
        'denial_reason',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'access_granted' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'status_label',
        'scan_type_label',
        'formatted_time'
    ];

    /**
     * Scan type constants
     */
    const SCAN_TYPE_QR = 'qr_code';
    const SCAN_TYPE_NFC = 'nfc_card';

    const SCAN_TYPES = [
        self::SCAN_TYPE_QR => 'QR-Code',
        self::SCAN_TYPE_NFC => 'NFC-Karte'
    ];

    /**
     * Boot method to register model events
     */
    protected static function boot()
    {
        parent::boot();

        // Log IP-Adresse automatisch beim Erstellen
        static::creating(function ($log) {
            if (request()) {
                $metadata = $log->metadata ?? [];
                $metadata['ip'] = request()->ip();
                $metadata['user_agent'] = request()->userAgent();
                $log->metadata = $metadata;
            }
        });

        // Broadcast event for live updates after creation
        static::created(function ($log) {
            broadcast(new ScannerAccessEvent($log))->toOthers();
        });
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Get the gym that owns the access log
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Get the scanner that created this log
     */
    public function scanner(): BelongsTo
    {
        return $this->belongsTo(GymScanner::class, 'device_number', 'device_number')
            ->where('gym_id', $this->gym_id);
    }

    /**
     * Get the member (user) if exists
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    // ==========================================
    // ACCESSORS & MUTATORS
    // ==========================================

    /**
     * Get the status label attribute
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->access_granted ? 'Gewährt' : 'Verweigert';
    }

    /**
     * Get the scan type label attribute
     */
    public function getScanTypeLabelAttribute(): string
    {
        return self::SCAN_TYPES[$this->scan_type] ?? 'Unbekannt';
    }

    /**
     * Get formatted time attribute
     */
    public function getFormattedTimeAttribute(): string
    {
        return $this->created_at->format('d.m.Y H:i:s');
    }

    /**
     * Get formatted date attribute
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->created_at->format('d.m.Y');
    }

    /**
     * Get time ago attribute
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if access was granted
     */
    public function wasGranted(): bool
    {
        return $this->access_granted === true;
    }

    /**
     * Check if access was denied
     */
    public function wasDenied(): bool
    {
        return $this->access_granted === false;
    }

    /**
     * Check if scan was QR code
     */
    public function isQrCode(): bool
    {
        return $this->scan_type === self::SCAN_TYPE_QR;
    }

    /**
     * Check if scan was NFC card
     */
    public function isNfcCard(): bool
    {
        return $this->scan_type === self::SCAN_TYPE_NFC;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope for granted access
     */
    public function scopeGranted(Builder $query): Builder
    {
        return $query->where('access_granted', true);
    }

    /**
     * Scope for denied access
     */
    public function scopeDenied(Builder $query): Builder
    {
        return $query->where('access_granted', false);
    }

    /**
     * Scope for QR code scans
     */
    public function scopeQrCode(Builder $query): Builder
    {
        return $query->where('scan_type', self::SCAN_TYPE_QR);
    }

    /**
     * Scope for NFC card scans
     */
    public function scopeNfcCard(Builder $query): Builder
    {
        return $query->where('scan_type', self::SCAN_TYPE_NFC);
    }

    /**
     * Scope for specific gym
     */
    public function scopeForGym(Builder $query, $gymId): Builder
    {
        return $query->where('gym_id', $gymId);
    }

    /**
     * Scope for specific scanner
     */
    public function scopeForScanner(Builder $query, string $deviceNumber): Builder
    {
        return $query->where('device_number', $deviceNumber);
    }

    /**
     * Scope for specific member
     */
    public function scopeForMember(Builder $query, string $memberId): Builder
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope for today's logs
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    /**
     * Scope for date range
     */
    public function scopeDateBetween(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('created_at', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay()
        ]);
    }

    /**
     * Scope for recent logs (last X hours)
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // ==========================================
    // BUSINESS LOGIC METHODS
    // ==========================================

    /**
     * Get denial category based on reason
     */
    public function getDenialCategory(): ?string
    {
        if ($this->access_granted) {
            return null;
        }

        $reason = strtolower($this->denial_reason ?? '');

        if (str_contains($reason, 'expired') || str_contains($reason, 'abgelaufen')) {
            return 'expired';
        }
        if (str_contains($reason, 'hash') || str_contains($reason, 'gefälscht')) {
            return 'invalid_hash';
        }
        if (str_contains($reason, 'mitglied') || str_contains($reason, 'membership')) {
            return 'no_membership';
        }
        if (str_contains($reason, 'format')) {
            return 'invalid_format';
        }

        return 'other';
    }

    /**
     * Get Bootstrap color class based on status
     */
    public function getStatusColorClass(): string
    {
        return $this->access_granted ? 'success' : 'danger';
    }

    /**
     * Get icon based on scan type
     */
    public function getScanTypeIcon(): string
    {
        return match($this->scan_type) {
            self::SCAN_TYPE_QR => '📱',
            self::SCAN_TYPE_NFC => '💳',
            default => '❓'
        };
    }

    /**
     * Check if log is suspicious (multiple denials in short time)
     */
    public function isSuspicious(): bool
    {
        if ($this->access_granted) {
            return false;
        }

        // Check for multiple failed attempts from same member in last 5 minutes
        $recentFailures = self::where('member_id', $this->member_id)
            ->denied()
            ->where('created_at', '>', $this->created_at->subMinutes(5))
            ->count();

        return $recentFailures >= 3;
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    /**
     * Create a new access log entry
     */
    public static function logAccess(
        int $gymId,
        string $deviceNumber,
        string $memberId,
        string $scanType,
        bool $granted,
        ?string $denialReason = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'gym_id' => $gymId,
            'device_number' => $deviceNumber,
            'member_id' => $memberId,
            'scan_type' => $scanType,
            'access_granted' => $granted,
            'denial_reason' => $granted ? null : $denialReason,
            'metadata' => $metadata
        ]);
    }

    /**
     * Get statistics for a gym
     */
    public static function getStatistics(int $gymId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = self::forGym($gymId);

        if ($startDate && $endDate) {
            $query->dateBetween($startDate, $endDate);
        } elseif ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $total = $query->count();
        $granted = (clone $query)->granted()->count();
        $denied = (clone $query)->denied()->count();

        return [
            'total' => $total,
            'granted' => $granted,
            'denied' => $denied,
            'success_rate' => $total > 0 ? round(($granted / $total) * 100, 2) : 0,
            'by_scan_type' => [
                'qr_code' => (clone $query)->qrCode()->count(),
                'nfc_card' => (clone $query)->nfcCard()->count()
            ],
            'by_device' => (clone $query)->select('device_number')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(access_granted) as granted')
                ->groupBy('device_number')
                ->get()
                ->map(function ($item) {
                    return [
                        'device' => $item->device_number,
                        'total' => (int) $item->total,
                        'granted' => (int) $item->granted,
                        'denied' => (int) $item->total - (int) $item->granted,
                        'success_rate' => $item->total > 0 ? round(((int) $item->granted / (int) $item->total) * 100, 2) : 0
                    ];
                })
                ->toArray()
        ];
    }

    /**
     * Get member access history
     */
    public static function getMemberHistory(string $memberId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return self::forMember($memberId)
            ->with(['gym', 'scanner'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Clean old logs (GDPR compliance)
     */
    public static function cleanOldLogs(int $daysToKeep = 90): int
    {
        return self::where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }
}
