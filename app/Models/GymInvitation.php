<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GymInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (GymInvitation $invitation): void {
            if (! $invitation->token) {
                $invitation->token = Str::random(64);
            }

            if (! $invitation->expires_at) {
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Whether the invitation is past its expiry date.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Human-readable German label for a gym team role.
     */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Admin',
            'staff' => 'Mitarbeiter',
            'trainer' => 'Trainer',
            default => ucfirst($role),
        };
    }
}
