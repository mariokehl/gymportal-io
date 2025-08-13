<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class MemberStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'member_status_history';

    protected $fillable = [
        'member_id',
        'old_status',
        'new_status',
        'reason',
        'changed_by',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['old_status_text', 'new_status_text', 'changed_by_name'];

    /**
     * Get the member associated with this status change
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the user who made this change
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get formatted status text for old status
     */
    public function getOldStatusTextAttribute(): string
    {
        return $this->getStatusText($this->old_status);
    }

    /**
     * Get formatted status text for new status
     */
    public function getNewStatusTextAttribute(): string
    {
        return $this->getStatusText($this->new_status);
    }

    /**
     * Get the full name of the user who made the change
     */
    public function getChangedByNameAttribute(): string
    {
        if (!$this->changedBy) {
            return 'System';
        }

        // Nutze die fullName() Methode des User Models
        return $this->changedBy->fullName();
    }

    /**
     * Get the role of the user who made the change
     */
    public function getChangedByRoleAttribute(): ?string
    {
        if (!$this->changedBy || !$this->changedBy->role) {
            return null;
        }

        return $this->changedBy->role->name;
    }

    /**
     * Helper to get localized status text
     */
    private function getStatusText(string $status): string
    {
        return [
            'active' => 'Aktiv',
            'inactive' => 'Inaktiv',
            'paused' => 'Pausiert',
            'overdue' => 'Überfällig',
            'pending' => 'Ausstehend'
        ][$status] ?? $status;
    }

    /**
     * Get the status change description
     */
    public function getDescriptionAttribute(): string
    {
        $user = $this->changed_by_name;
        $reason = $this->reason ? " - {$this->reason}" : '';

        return sprintf(
            '%s hat den Status von "%s" zu "%s" geändert%s',
            $user,
            $this->old_status_text,
            $this->new_status_text,
            $reason
        );
    }

    /**
     * Get detailed change information
     */
    public function getChangeDetailsAttribute(): array
    {
        return [
            'user' => $this->changedBy ? [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->fullName(),
                'email' => $this->changedBy->email,
                'role' => $this->changed_by_role
            ] : null,
            'from_status' => [
                'value' => $this->old_status,
                'text' => $this->old_status_text
            ],
            'to_status' => [
                'value' => $this->new_status,
                'text' => $this->new_status_text
            ],
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'timestamp' => $this->created_at->toISOString(),
            'formatted_date' => $this->created_at->format('d.m.Y H:i'),
            'relative_time' => $this->created_at->diffForHumans()
        ];
    }

    /**
     * Check if this was an automatic change
     */
    public function getIsAutomaticAttribute(): bool
    {
        return !$this->changed_by ||
               ($this->metadata['action_type'] ?? null) === 'auto_activation' ||
               in_array($this->metadata['triggered_by'] ?? null, [
                   'payment_overdue',
                   'payment_resolved',
                   'member_inactivation',
                   'auto_activation',
                   'system'
               ]);
    }

    /**
     * Check if this was a manual change by a user
     */
    public function getIsManualAttribute(): bool
    {
        return !$this->is_automatic;
    }

    /**
     * Get the trigger type
     */
    public function getTriggerTypeAttribute(): ?string
    {
        return $this->metadata['triggered_by'] ?? null;
    }

    /**
     * Scope for getting history by member
     */
    public function scopeForMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope for getting recent changes
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for manual changes only
     */
    public function scopeManual($query)
    {
        return $query->whereNotNull('changed_by')
                    ->where(function($q) {
                        $q->whereNull('metadata->triggered_by')
                          ->orWhere('metadata->action_source', 'manual_update');
                    });
    }

    /**
     * Scope for automatic changes only
     */
    public function scopeAutomatic($query)
    {
        return $query->where(function($q) {
            $q->whereNull('changed_by')
              ->orWhereNotNull('metadata->triggered_by');
        });
    }

    /**
     * Scope for changes by a specific user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('changed_by', $userId);
    }

    /**
     * Scope for specific status transitions
     */
    public function scopeTransition($query, string $from, string $to)
    {
        return $query->where('old_status', $from)
                    ->where('new_status', $to);
    }

    /**
     * Create a new status history entry
     */
    public static function recordChange(
        Member $member,
        string $oldStatus,
        string $newStatus,
        ?string $reason = null,
        ?array $metadata = null
    ): self {
        /** @var User $user */
        $user = Auth::user();

        return self::create([
            'member_id' => $member->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => $user ? $user->id : null,
            'metadata' => array_merge($metadata ?? [], [
                'timestamp' => now()->toISOString(),
                'action_source' => $metadata['action_source'] ?? 'manual_update',
                'user_role' => $user && $user->role ? $user->role->slug : null,
                'user_name' => $user ? $user->fullName() : null
            ])
        ]);
    }

    /**
     * Record an automatic status change
     */
    public static function recordAutomaticChange(
        Member $member,
        string $oldStatus,
        string $newStatus,
        string $triggeredBy,
        ?string $reason = null,
        ?array $additionalMetadata = null
    ): self {
        return self::create([
            'member_id' => $member->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => null, // System change
            'metadata' => array_merge($additionalMetadata ?? [], [
                'triggered_by' => $triggeredBy,
                'timestamp' => now()->toISOString(),
                'action_source' => 'system'
            ])
        ]);
    }

    /**
     * Get summary statistics for a member
     */
    public static function getMemberStatistics(int $memberId): array
    {
        $history = self::forMember($memberId)->with('changedBy')->get();

        return [
            'total_changes' => $history->count(),
            'manual_changes' => $history->filter(fn($h) => $h->is_manual)->count(),
            'automatic_changes' => $history->filter(fn($h) => $h->is_automatic)->count(),
            'last_change' => $history->first()?->change_details,
            'most_common_status' => $history->groupBy('new_status')
                ->map->count()
                ->sortDesc()
                ->keys()
                ->first(),
            'changes_by_user' => $history->filter(fn($h) => $h->changed_by)
                ->groupBy('changed_by')
                ->map(function ($group) {
                    $user = $group->first()->changedBy;
                    return [
                        'user_id' => $user->id,
                        'user_name' => $user->fullName(),
                        'user_role' => $user->role ? $user->role->name : null,
                        'changes_count' => $group->count()
                    ];
                })
                ->values()
        ];
    }

    /**
     * Get formatted history for display
     */
    public static function getFormattedHistory(int $memberId, int $limit = 20): \Illuminate\Support\Collection
    {
        return self::forMember($memberId)
            ->with(['changedBy.role'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'description' => $history->description,
                    'change_details' => $history->change_details,
                    'is_automatic' => $history->is_automatic,
                    'created_at' => $history->created_at,
                    'relative_time' => $history->created_at->diffForHumans()
                ];
            });
    }
}
