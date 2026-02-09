<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gym_id',
        'name',
        'description',
        'price',
        'setup_fee',
        'trial_period_days',
        'trial_price',
        'billing_cycle',
        'is_active',
        'is_free_trial_plan',
        'commitment_months',
        'cancellation_period',
        'cancellation_period_unit',
        'auto_renew_type',
        'features',
        'widget_display_options',
        'sort_order',
        'highlight',
        'badge_text',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'trial_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_free_trial_plan' => 'boolean',
        'highlight' => 'boolean',
        'features' => 'array',
        'widget_display_options' => 'array',
    ];

    protected $appends = ['formatted_price', 'billing_cycle_text', 'cancellation_period_in_days', 'formatted_cancellation_period'];

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function widgetRegistrations()
    {
        return $this->hasMany(WidgetRegistration::class);
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2, ',', '.') . ' €';
    }

    public function getFormattedTrialPriceAttribute()
    {
        return number_format($this->trial_price, 2, ',', '.') . ' €';
    }

    public function getFormattedSetupFeeAttribute()
    {
        return number_format($this->setup_fee, 2, ',', '.') . ' €';
    }

    public function getBillingCycleTextAttribute()
    {
        return [
            'monthly' => 'Monatlich',
            'quarterly' => 'Vierteljährlich',
            'yearly' => 'Jährlich',
        ][$this->billing_cycle] ?? $this->billing_cycle;
    }

    public function getHasTrialAttribute()
    {
        return $this->trial_period_days > 0;
    }

    public function getWidgetFeaturesAttribute()
    {
        $defaults = [
            'show_in_widget' => true,
            'highlight' => false,
            'badge_text' => null,
            'features_included' => [],
            'features_addon' => [],
        ];

        return array_merge($defaults, $this->widget_display_options ?? []);
    }

    public function scopeForWidget($query)
    {
        return $query->where('is_active', true)
            ->whereJsonContains('widget_display_options->show_in_widget', true)
            ->orderBy('sort_order')
            ->orderBy('price');
    }

    public function scopeHighlighted($query)
    {
        return $query->where('highlight', true);
    }

    /**
     * Get cancellation period in days for calculation purposes.
     * Converts months to days (using 30 days per month as approximation).
     */
    public function getCancellationPeriodInDaysAttribute(): int
    {
        $period = $this->cancellation_period ?? 0;
        $unit = $this->cancellation_period_unit ?? 'days';

        if ($unit === 'months') {
            return $period * 30;
        }

        return $period;
    }

    /**
     * Get formatted cancellation period text.
     */
    public function getFormattedCancellationPeriodAttribute(): string
    {
        $period = $this->cancellation_period ?? 0;
        $unit = $this->cancellation_period_unit ?? 'days';

        if ($unit === 'months') {
            return $period . ' ' . ($period === 1 ? 'Monat' : 'Monate');
        }

        return $period . ' ' . ($period === 1 ? 'Tag' : 'Tage');
    }
}
