<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time-boxed promotional price for the first months of a membership plan.
 *
 * Phases run consecutively from contract start in `sort_order`. Each phase
 * overrides the plan price for `duration_months`; once all phases have
 * elapsed, the plan's regular price applies.
 */
class MembershipPlanDiscountPhase extends Model
{
    use HasFactory;

    protected $fillable = [
        'membership_plan_id',
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

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
