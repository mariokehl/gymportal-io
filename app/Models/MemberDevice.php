<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberDevice extends Model
{
    protected $fillable = [
        'member_id',
        'device_token',
        'device_name',
        'last_used_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public const MAX_DEVICES_PER_MEMBER = 2;

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Check if the member has reached the device limit.
     */
    public static function hasReachedLimit(int $memberId): bool
    {
        return static::where('member_id', $memberId)->count() >= static::MAX_DEVICES_PER_MEMBER;
    }

    /**
     * Register a device token for a member, or update last_used_at if already registered.
     */
    public static function registerForMember(
        int $memberId,
        string $deviceToken,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return static::updateOrCreate(
            ['device_token' => $deviceToken],
            [
                'member_id' => $memberId,
                'last_used_at' => now(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_name' => self::parseDeviceName($userAgent),
            ]
        );
    }

    /**
     * Extract a readable device name from the user agent string.
     */
    private static function parseDeviceName(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        // Try to extract device info from common patterns
        if (preg_match('/\(([^)]+)\)/', $userAgent, $matches)) {
            $info = $matches[1];
            // Simplify common patterns
            if (str_contains($info, 'iPhone')) return 'iPhone';
            if (str_contains($info, 'iPad')) return 'iPad';
            if (preg_match('/Android[^;]*;\s*([^)]+)/', $info, $m)) return trim($m[1]);
        }

        return null;
    }
}
