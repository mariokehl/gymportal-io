<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Addon extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * One-off service such as a trainer induction.
     */
    public const SERVICE_TYPE_ADDITIONAL = 'additional';

    /**
     * Consumable service (drinks, sauna) settled through a device.
     */
    public const SERVICE_TYPE_USAGE = 'usage';

    public const SERVICE_TYPES = [
        self::SERVICE_TYPE_ADDITIONAL,
        self::SERVICE_TYPE_USAGE,
    ];

    /**
     * Charged once at contract start.
     */
    public const BILLING_TYPE_ONE_TIME = 'one_time';

    /**
     * Charged monthly, in sync with the membership fee.
     */
    public const BILLING_TYPE_RECURRING = 'recurring';

    public const BILLING_TYPES = [
        self::BILLING_TYPE_ONE_TIME,
        self::BILLING_TYPE_RECURRING,
    ];

    public const USAGE_PERIOD_SINGLE = 'single';

    public const USAGE_PERIOD_FIXED = 'fixed_period';

    public const USAGE_PERIOD_FULL_DAY = 'full_day';

    public const USAGE_PERIODS = [
        self::USAGE_PERIOD_SINGLE,
        self::USAGE_PERIOD_FIXED,
        self::USAGE_PERIOD_FULL_DAY,
    ];

    public const DURATION_UNITS = ['hours', 'days', 'weeks'];

    public const QUOTA_INTERVALS = ['day', 'week', 'month'];

    /**
     * Show the monthly price in the widget.
     */
    public const PRICE_DISPLAY_MONTHLY = 'monthly';

    /**
     * Show the computed weekly equivalent in the widget. Purely a display
     * choice — billing stays monthly, in sync with the membership fee.
     */
    public const PRICE_DISPLAY_WEEKLY = 'weekly';

    public const PRICE_DISPLAYS = [
        self::PRICE_DISPLAY_MONTHLY,
        self::PRICE_DISPLAY_WEEKLY,
    ];

    /**
     * Weeks per month (12 months / 52 weeks), used to derive the weekly price.
     */
    public const WEEKS_PER_MONTH = 52 / 12;

    protected $fillable = [
        'gym_id',
        'name',
        'description',
        'service_type',
        'billing_type',
        'trial_rest_of_month',
        'usage_period',
        'usage_duration',
        'usage_duration_unit',
        'quota_amount',
        'quota_interval',
        'settled_via_device',
        'price',
        'price_display',
        'payment_method',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'trial_rest_of_month' => 'boolean',
        'settled_via_device' => 'boolean',
        'usage_duration' => 'integer',
        'quota_amount' => 'integer',
    ];

    protected $appends = ['formatted_price', 'weekly_price', 'formatted_weekly_price'];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function membershipPlans(): BelongsToMany
    {
        return $this->belongsToMany(MembershipPlan::class)
            ->withPivot('mode')
            ->withTimestamps();
    }

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class)
            ->withPivot(
                'mode',
                'price',
                'booked_at',
                'payment_id',
                'completed_at',
                'completed_by',
                'cancelled_at',
                'cancellation_effective_at',
                'cancelled_by'
            )
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUsageServices(Builder $query): Builder
    {
        return $query->where('service_type', self::SERVICE_TYPE_USAGE);
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('billing_type', self::BILLING_TYPE_RECURRING);
    }

    public function isUsageService(): bool
    {
        return $this->service_type === self::SERVICE_TYPE_USAGE;
    }

    public function isRecurring(): bool
    {
        return $this->billing_type === self::BILLING_TYPE_RECURRING;
    }

    /**
     * A usage service without a quota amount is an unlimited flat rate.
     */
    public function hasUnlimitedQuota(): bool
    {
        return $this->isUsageService() && $this->quota_amount === null;
    }

    /**
     * Whether the widget shows the weekly equivalent instead of the monthly
     * price. Only recurring add-ons are billed monthly, so only those have a
     * meaningful weekly conversion.
     */
    public function showsWeeklyPrice(): bool
    {
        return $this->isRecurring() && $this->price_display === self::PRICE_DISPLAY_WEEKLY;
    }

    /**
     * Monthly price converted to a weekly comparison figure. This is never
     * charged — the monthly price stays the amount billed.
     */
    public function getWeeklyPriceAttribute(): float
    {
        return round((float) $this->price / self::WEEKS_PER_MONTH, 2);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2, ',', '.').' €';
    }

    public function getFormattedWeeklyPriceAttribute(): string
    {
        return number_format($this->weekly_price, 2, ',', '.').' €';
    }
}
