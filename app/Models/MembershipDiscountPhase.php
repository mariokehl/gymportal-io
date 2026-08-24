<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A discount phase as it applied to one membership at contract signup.
 *
 * This is a frozen copy of the plan's phase, not a reference to it: billing
 * resolves the monthly price from these rows, so editing the plan's discount
 * ladder later never changes what an existing member pays.
 */
class MembershipDiscountPhase extends Model
{
    use HasFactory;

    protected $fillable = [
        'membership_id',
        'version_key',
        'sort_order',
        'duration_months',
        'price',
        'original_price',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'duration_months' => 'integer',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
    ];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
