<?php

namespace App\Models;

use App\Services\CreditLedgerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Append-only, tamper-evident credit ledger entry for a member.
 *
 * Amounts are persisted as encrypted integer cents and every row is protected
 * by an HMAC chain (see {@see CreditLedgerService}). Rows are
 * immutable: updates and deletes are blocked at the model level so the balance
 * cannot be silently rewritten (e.g. turning 3 € into 300 €).
 */
class MemberCreditLedger extends Model
{
    public const TYPE_TOPUP = 'topup';

    public const TYPE_REDEMPTION = 'redemption';

    public const TYPE_REFUND = 'refund';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'gym_id',
        'member_id',
        'payment_id',
        'created_by',
        'type',
        'encrypted_amount',
        'encrypted_balance_after',
        'entry_hash',
        'prev_hash',
        'description',
    ];

    /**
     * Never expose the encrypted amounts or the integrity hashes to the frontend.
     */
    protected $hidden = [
        'encrypted_amount',
        'encrypted_balance_after',
        'entry_hash',
        'prev_hash',
    ];

    protected static function boot(): void
    {
        parent::boot();

        // Enforce append-only semantics: an existing entry must never change.
        static::updating(function (): void {
            throw new RuntimeException('Credit ledger entries are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Credit ledger entries are immutable and cannot be deleted.');
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
