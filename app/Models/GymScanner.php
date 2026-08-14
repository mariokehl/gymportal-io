<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GymScanner extends Model
{
    /**
     * Checks whether the member may enter and books check-in or check-out.
     */
    public const TASK_CHECKIN_CHECKOUT = 'checkin_checkout';

    /**
     * Controls entry only and records the check-in.
     */
    public const TASK_CHECKIN = 'checkin';

    /**
     * Controls the exit only and checks members out.
     */
    public const TASK_CHECKOUT = 'checkout';

    /**
     * Drink dispenser. Verifies the quota of a usage add-on and releases the tap.
     */
    public const TASK_DISPENSER = 'dispenser';

    /**
     * Restricts an area (e.g. sauna) based on booked usage add-ons.
     */
    public const TASK_AREA_CONTROL = 'area_control';

    /**
     * Shows a check-in dialog at the counter for manual approval.
     */
    public const TASK_MANUAL = 'manual';

    public const DEVICE_TASKS = [
        self::TASK_CHECKIN,
        self::TASK_CHECKIN_CHECKOUT,
        self::TASK_CHECKOUT,
        self::TASK_DISPENSER,
        self::TASK_AREA_CONTROL,
        self::TASK_MANUAL,
    ];

    /**
     * Task a device gets when none is given. Most gyms run a single scanner at
     * the entrance and handle the check-out in software, so plain check-in is
     * the realistic default. Mirrors the column default in the database.
     */
    public const DEFAULT_DEVICE_TASK = self::TASK_CHECKIN;

    /**
     * Tasks that settle against the quota of a linked usage add-on.
     */
    public const ADDON_LINKED_TASKS = [
        self::TASK_DISPENSER,
        self::TASK_AREA_CONTROL,
    ];

    /**
     * Months the token expiry is pre-filled with when a device is created.
     *
     * A suggestion, not a default: the form offers it and the operator can
     * opt out. Deliberately not applied in the model, so devices created
     * outside the form (seeders, imports) keep a token that never expires —
     * an expiring token locks members out of the gym.
     */
    public const DEFAULT_TOKEN_LIFETIME_MONTHS = 12;

    protected $fillable = [
        'gym_id',
        'device_number',
        'device_name',
        'device_task',
        'addon_id',
        'enforce_quota',
        'ip_address',
        'is_active',
        'allowed_ips',
        'token_expires_at',
    ];

    protected $hidden = [
        'api_token', // Token nie in API Responses zeigen
        'api_token_hash',
    ];

    /**
     * Exposed so the UI can tell whether a config file is still obtainable.
     */
    protected $appends = [
        'config_downloadable',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'enforce_quota' => 'boolean',
        'last_seen_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'locked_until' => 'datetime',
        'allowed_ips' => 'array',
    ];

    /**
     * The plaintext token, readable only in the request that generated it.
     *
     * Persisted is the hash alone, so this is the single opportunity to hand
     * the token to the operator. Reading it later yields null.
     */
    private ?string $plainTextToken = null;

    /**
     * Boot-Methode - Generiert automatisch Token bei Erstellung
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($scanner) {
            if (empty($scanner->api_token_hash)) {
                $scanner->setToken($scanner->generateUniqueToken());
            }

            // Automatische Device-Nummer wenn nicht gesetzt
            if (empty($scanner->device_number)) {
                $scanner->device_number = $scanner->generateDeviceNumber();
            }
        });
    }

    /**
     * Hash a plaintext token for storage and lookup.
     *
     * SHA-256 without a salt on purpose: the value is high-entropy random, not
     * a password, and the lookup has to find the row by hash. Same approach as
     * Laravel Sanctum.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Find the device belonging to a plaintext token.
     *
     * Compares hashes, so the query never carries the token itself. Devices
     * provisioned before the hash column existed still authenticate through
     * the plaintext fallback until they are re-provisioned.
     */
    public static function findByToken(string $token): ?self
    {
        $scanner = self::where('api_token_hash', self::hashToken($token))->first();

        if ($scanner) {
            return $scanner;
        }

        return self::whereNotNull('api_token')
            ->where('api_token', $token)
            ->first();
    }

    /**
     * Generiert ein eindeutiges API Token
     */
    public function generateUniqueToken(): string
    {
        do {
            // Format: "scan_" + 32 zufällige Zeichen
            $token = 'scan_'.Str::random(32);
        } while (self::where('api_token_hash', self::hashToken($token))->exists());

        return $token;
    }

    /**
     * Store a token as its hash and keep the plaintext for this request only.
     */
    private function setToken(string $token): void
    {
        $this->api_token_hash = self::hashToken($token);

        // New devices never get a plaintext copy — only the operator does,
        // through the response to the request that created the device.
        $this->api_token = null;
        $this->plainTextToken = $token;
    }

    /**
     * The plaintext token, if this instance just generated one.
     */
    public function getPlainTextToken(): ?string
    {
        // Devices from before the hash migration still carry their plaintext,
        // which keeps their config downloadable until they are re-provisioned.
        return $this->plainTextToken ?? $this->api_token;
    }

    /**
     * Whether a config file can still be generated for this device.
     *
     * False once the token exists only as a hash — the config needs the
     * plaintext, and it cannot be recovered. Serialised for the UI so the
     * download stays disabled instead of failing on click.
     */
    public function getConfigDownloadableAttribute(): bool
    {
        return $this->api_token !== null;
    }

    /**
     * Generiert eine neue Device-Nummer
     */
    private function generateDeviceNumber(): string
    {
        $lastNumber = self::where('gym_id', $this->gym_id)
            ->orderBy('device_number', 'desc')
            ->value('device_number');

        if (! $lastNumber) {
            return '001';
        }

        return str_pad((int) $lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Erneuert das API Token
     */
    public function regenerateToken(): string
    {
        $token = $this->generateUniqueToken();

        // Drops any plaintext this device still carried from before the hash
        // migration, so regenerating also completes its migration.
        $this->setToken($token);
        $this->save();

        return $token;
    }

    /**
     * Prüft ob der Scanner aktiv und nicht gesperrt ist
     */
    public function isAccessible(): bool
    {
        // Inaktiv?
        if (! $this->is_active) {
            return false;
        }

        // Token abgelaufen?
        if ($this->token_expires_at && $this->token_expires_at->isPast()) {
            return false;
        }

        // Temporär gesperrt (Brute-Force Schutz)?
        if ($this->locked_until && $this->locked_until->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Prüft ob eine IP-Adresse erlaubt ist
     */
    public function isIpAllowed(string $ip): bool
    {
        // Wenn keine IP-Whitelist definiert, alle erlauben
        if (empty($this->allowed_ips)) {
            return true;
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Registers a failed attempt
     */
    public function registerFailedAttempt(): void
    {
        $this->increment('failed_attempts');

        // Lock for 15 minutes after 5 failed attempts
        if ($this->failed_attempts >= 5) {
            $this->locked_until = now()->addMinutes(15);
            $this->save();
        }
    }

    /**
     * Resets the failed attempts.
     *
     * Both columns are guarded, so they are written past mass assignment —
     * update() would silently drop them and leave the scanner locked.
     */
    public function resetFailedAttempts(): void
    {
        $this->forceFill([
            'failed_attempts' => 0,
            'locked_until' => null,
        ])->save();
    }

    /**
     * Aktualisiert den "last seen" Zeitstempel
     */
    public function touch($attribute = null)
    {
        $this->last_seen_at = now();
        $this->ip_address = request()->ip();

        return $this->save();
    }

    /**
     * Whether this device settles against the quota of a linked usage add-on.
     */
    public function isAddonLinked(): bool
    {
        return in_array($this->device_task, self::ADDON_LINKED_TASKS, true);
    }

    public function isDispenser(): bool
    {
        return $this->device_task === self::TASK_DISPENSER;
    }

    /**
     * Relationships
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * The usage add-on whose quota this device settles against. Only set for
     * dispenser and area-control devices.
     */
    public function addon()
    {
        return $this->belongsTo(Addon::class);
    }

    public function accessLogs()
    {
        return $this->hasMany(ScannerAccessLog::class, 'device_number', 'device_number')
            ->where('gym_id', $this->gym_id);
    }
}
