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
        'commitment_months',
        'cancellation_period_days',
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
        'highlight' => 'boolean',
        'features' => 'array',
        'widget_display_options' => 'array',
    ];

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
}
