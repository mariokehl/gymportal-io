<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An external automation (AI agent, integration) allowed to use the Agent API
 * of exactly one gym.
 */
class GymApiClient extends Model
{
    public const TOKEN_PREFIX = 'gpa_';

    /**
     * Every ability the Agent API knows, one per area, so a token only sees
     * what its job needs.
     */
    public const ABILITIES = [
        'plans.read',
        'members.read',
        'contracts.read',
        'access.read',
        'payments.read',
        'stats.read',
    ];

    public const READ_ABILITIES = [
        'plans.read',
        'members.read',
        'contracts.read',
        'access.read',
        'payments.read',
        'stats.read',
    ];

    protected $fillable = [
        'gym_id',
        'name',
        'purpose',
        'token_hash',
        'token_prefix',
        'abilities',
        'expires_at',
        'revoked_at',
        'last_used_at',
        'last_used_ip',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AgentApiLog::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Creates a client and returns it together with the plaintext token, which
     * is not stored anywhere and cannot be shown again.
     *
     * @param  list<string>  $abilities
     * @return array{0: self, 1: string}
     */
    public static function issue(Gym $gym, string $name, string $purpose, array $abilities, ?\DateTimeInterface $expiresAt = null): array
    {
        $unknown = array_diff($abilities, self::ABILITIES);

        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown abilities: '.implode(', ', $unknown));
        }

        if (trim($purpose) === '') {
            throw new \InvalidArgumentException('A purpose is required.');
        }

        $token = self::TOKEN_PREFIX.Str::random(48);

        $client = self::create([
            'gym_id' => $gym->id,
            'name' => $name,
            'purpose' => $purpose,
            'token_hash' => self::hashToken($token),
            'token_prefix' => substr($token, 0, 10),
            'abilities' => array_values(array_unique($abilities)),
            'expires_at' => $expiresAt,
        ]);

        return [$client, $token];
    }

    public static function findByToken(string $token): ?self
    {
        if (! str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }

        return self::where('token_hash', self::hashToken($token))->first();
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function can(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true);
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }
}
