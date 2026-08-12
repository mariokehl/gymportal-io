<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single claim inside a collection case.
 *
 * The kind determines which list the claim is sent in when the case is
 * transmitted to DIAGONAL (see App\Services\Diagonal\DiagonalCaseMapper).
 */
class CollectionClaim extends Model
{
    use HasFactory;

    /** Main claim, transmitted as invoiceList. */
    public const KIND_PRINCIPAL = 'principal';

    /** Dunning fee, transmitted as dunningList. */
    public const KIND_DUNNING = 'dunning';

    /** Handover flat fee, transmitted as expensesList. */
    public const KIND_FLAT = 'flat';

    public const KIND_LABELS = [
        self::KIND_PRINCIPAL => 'Hauptforderung',
        self::KIND_DUNNING => 'Mahngebühr',
        self::KIND_FLAT => 'Übergabepauschale',
    ];

    protected $fillable = [
        'gym_id',
        'collection_case_id',
        'payment_id',
        'description',
        'due_date',
        'amount',
        'paid_amount',
        'kind',
        'written_off',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'written_off' => 'boolean',
    ];

    protected $appends = ['open_amount', 'status_text', 'status_color', 'kind_label'];

    public function getKindLabelAttribute(): string
    {
        return self::KIND_LABELS[$this->kind] ?? 'Forderung';
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function collectionCase(): BelongsTo
    {
        return $this->belongsTo(CollectionCase::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getOpenAmountAttribute(): string
    {
        if ($this->written_off) {
            return '0.00';
        }

        return number_format(max(0, (float) $this->amount - (float) $this->paid_amount), 2, '.', '');
    }

    public function getStatusTextAttribute(): string
    {
        if ($this->written_off) {
            return 'Ausgebucht';
        }

        if ((float) $this->open_amount <= 0.001) {
            return 'Bezahlt';
        }

        return (float) $this->paid_amount > 0 ? 'Teilzahlung' : 'Im Inkasso';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status_text) {
            'Ausgebucht' => 'gray',
            'Bezahlt' => 'green',
            'Teilzahlung' => 'yellow',
            default => 'orange',
        };
    }
}
