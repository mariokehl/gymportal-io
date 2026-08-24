<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A time-boxed promotional price for the first months of a membership plan.
 *
 * Phases run consecutively from contract start in `sort_order`. Each phase
 * overrides the plan price for `duration_months`; once all phases have
 * elapsed, the plan's regular price applies.
 */
class MembershipPlanDiscountPhase extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Guarantee every phase carries a version key, even when it was written
     * outside MembershipPlanDiscountService::sync() — by an import, a seeder
     * or a test fixture. A phase without one could not be traced back from a
     * membership that was signed on it.
     */
    protected static function booted(): void
    {
        static::creating(function (self $phase): void {
            $phase->version_key ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'membership_plan_id',
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

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }
}
