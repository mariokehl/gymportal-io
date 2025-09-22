<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'code',
        'expires_at',
        'used',
        'used_at',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
        'used_at' => 'datetime'
    ];

    protected $hidden = [
        'code', // Code nicht in JSON responses ausgeben (Sicherheit)
    ];

    // Relationships
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    // Scopes
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now())
                    ->where('used', false);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeUsed($query)
    {
        return $query->where('used', true);
    }

    public function scopeForMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    // Methods
    public function isValid(): bool
    {
        return !$this->used && $this->expires_at->gt(now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->lte(now());
    }

    public function markAsUsed(): void
    {
        $this->update([
            'used' => true,
            'used_at' => now(),
        ]);
    }

    public function getTimeUntilExpiry(): ?int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return $this->expires_at->diffInSeconds(now());
    }

    public function getFormattedExpiryTime(): string
    {
        if ($this->isExpired()) {
            return 'Abgelaufen';
        }

        $seconds = $this->getTimeUntilExpiry();
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes > 0) {
            return sprintf('%d:%02d Minuten', $minutes, $remainingSeconds);
        }

        return sprintf('%d Sekunden', $remainingSeconds);
    }

    // Static Methods
    public static function generateUniqueCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->valid()->exists());

        return $code;
    }

    public static function createForMember(Member $member, int $expiryMinutes = 10): self
    {
        // Alte Codes für dieses Mitglied invalidieren
        self::where('member_id', $member->id)
            ->where('used', false)
            ->update(['used' => true]);

        return self::create([
            'member_id' => $member->id,
            'code' => self::generateUniqueCode(),
            'expires_at' => now()->addMinutes($expiryMinutes),
            'used' => false,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public static function findValidCode(string $code, int $memberId): ?self
    {
        return self::where('code', $code)
                  ->where('member_id', $memberId)
                  ->valid()
                  ->first();
    }

    // Boot method for model events
    protected static function boot()
    {
        parent::boot();

        // Cleanup old codes when creating new ones
        static::created(function ($loginCode) {
            // Delete expired codes older than 1 day
            self::where('expires_at', '<', now()->subDay())->delete();
        });
    }

    // Accessors
    public function getIsValidAttribute(): bool
    {
        return $this->isValid();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }
}
