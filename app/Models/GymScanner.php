<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GymScanner extends Model
{
    protected $fillable = [
        'gym_id',
        'device_number',
        'device_name',
        'ip_address',
        'is_active',
        'allowed_ips',
        'token_expires_at'
    ];

    protected $hidden = [
        'api_token' // Token nie in API Responses zeigen
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'locked_until' => 'datetime',
        'allowed_ips' => 'array'
    ];

    /**
     * Boot-Methode - Generiert automatisch Token bei Erstellung
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($scanner) {
            if (empty($scanner->api_token)) {
                $scanner->api_token = $scanner->generateUniqueToken();
            }

            // Automatische Device-Nummer wenn nicht gesetzt
            if (empty($scanner->device_number)) {
                $scanner->device_number = $scanner->generateDeviceNumber();
            }
        });
    }

    /**
     * Generiert ein eindeutiges API Token
     */
    public function generateUniqueToken(): string
    {
        do {
            // Format: "scan_" + 32 zufällige Zeichen
            $token = 'scan_' . Str::random(32);
        } while (self::where('api_token', $token)->exists());

        return $token;
    }

    /**
     * Generiert eine neue Device-Nummer
     */
    private function generateDeviceNumber(): string
    {
        $lastNumber = self::where('gym_id', $this->gym_id)
            ->orderBy('device_number', 'desc')
            ->value('device_number');

        if (!$lastNumber) {
            return '001';
        }

        return str_pad((int)$lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Erneuert das API Token
     */
    public function regenerateToken(): string
    {
        $this->api_token = $this->generateUniqueToken();
        $this->save();

        return $this->api_token;
    }

    /**
     * Prüft ob der Scanner aktiv und nicht gesperrt ist
     */
    public function isAccessible(): bool
    {
        // Inaktiv?
        if (!$this->is_active) {
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
     * Registriert einen fehlgeschlagenen Versuch
     */
    public function registerFailedAttempt(): void
    {
        $this->increment('failed_attempts');

        // Nach 5 Fehlversuchen: 15 Minuten sperren
        if ($this->failed_attempts >= 5) {
            $this->locked_until = now()->addMinutes(15);
            $this->save();
        }
    }

    /**
     * Setzt die Fehlversuche zurück
     */
    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_attempts' => 0,
            'locked_until' => null
        ]);
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
     * Relationships
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function accessLogs()
    {
        return $this->hasMany(ScannerAccessLog::class, 'device_number', 'device_number')
            ->where('gym_id', $this->gym_id);
    }
}
