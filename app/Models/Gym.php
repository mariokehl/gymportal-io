<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Gym extends Model
{
    const DEFAULT_ORGANIZATION_NAME = 'Mein Fitnessstudio';

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'address',
        'city',
        'postal_code',
        'country',
        'phone',
        'email',
        'website',
        'logo_path',
        'owner_id',
        'paddle_subscription_id',
        'subscription_status',
        'subscription_plan',
        'subscription_ends_at',
        'mollie_config',
        'api_key',
        'widget_enabled',
        'widget_settings',
        'trial_ends_at', // Neues Feld für explizite Testphase
    ];

    protected $casts = [
        'subscription_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'widget_settings' => 'array',
        'widget_enabled' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gym) {
            if (empty($gym->api_key)) {
                $gym->api_key = $gym->generateApiKey();
            }

            // Setze Testphase auf 30 Tage ab Erstellung
            if (empty($gym->trial_ends_at)) {
                $gym->trial_ends_at = now()->addDays(30);
            }

            $gym->generateSlug();
        });

        static::updating(function ($gym) {
            if ($gym->isDirty('name')) {
                $gym->generateSlug();
            }
        });
    }

    // Existing relationships
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function staff()
    {
        return $this->belongsToMany(User::class)->wherePivot('role', 'staff');
    }

    public function trainers()
    {
        return $this->belongsToMany(User::class)->wherePivot('role', 'trainer');
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function membershipPlans()
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function checkIns()
    {
        return $this->hasMany(CheckIn::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // New Mollie-related relationships
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices()
    {
        //return $this->hasMany(Invoice::class);
    }

    // Subscription & Trial Methods
    public function isInTrial(): bool
    {
        return $this->trial_ends_at && now()->lt($this->trial_ends_at);
    }

    public function trialDaysLeft(): int
    {
        if (!$this->isInTrial()) {
            return 0;
        }

        return now()->diffInDays($this->trial_ends_at);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === 'active' &&
               $this->subscription_ends_at &&
               $this->subscription_ends_at->gt(now());
    }

    public function canAccessPremiumFeatures(): bool
    {
        return $this->isInTrial() || $this->hasActiveSubscription();
    }

    public function getSubscriptionStatusLabel(): string
    {
        if ($this->hasActiveSubscription()) {
            return 'Aktiv';
        }

        if ($this->isInTrial()) {
            return 'Testphase';
        }

        return 'Abgelaufen';
    }

    public function extendTrial(int $days = 30): void
    {
        $this->update([
            'trial_ends_at' => now()->addDays($days)
        ]);
    }

    // Existing methods
    public function getActiveMembersCount()
    {
        return $this->members()->where('status', 'active')->count();
    }

    public function getInactiveMembersCount()
    {
        return $this->members()->where('status', '!=', 'active')->count();
    }

    public function getSubscriptionIsActive()
    {
        return $this->hasActiveSubscription();
    }

    // New Mollie-related methods
    public function setMollieConfigAttribute($value)
    {
        // If $value is an array, convert to JSON
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $this->attributes['mollie_config'] = Crypt::encryptString($value);
    }

    public function getMollieConfigAttribute($value)
    {
        // Try to decrypt → JSON → Array
        try {
            return json_decode(Crypt::decryptString($value), true);
        } catch (\Exception $e) {
            // Fallback if decryption fails (e.g. old data)
            return null;
        }
    }

    public function hasMollieConfigured(): bool
    {
        return !empty($this->mollie_config) &&
               isset($this->mollie_config['api_key']) &&
               isset($this->mollie_config['enabled_methods']) &&
               count($this->mollie_config['enabled_methods']) > 0;
    }

    public function getMollieApiKey(): ?string
    {
        return $this->mollie_config['api_key'] ?? null;
    }

    public function getMollieEnabledMethods(): array
    {
        return $this->mollie_config['enabled_methods'] ?? [];
    }

    public function getMollieWebhookUrl(): ?string
    {
        return $this->mollie_config['webhook_url'] ?? null;
    }

    public function getMollieRedirectUrl(): ?string
    {
        return $this->mollie_config['redirect_url'] ?? null;
    }

    public function isInTestMode(): bool
    {
        return $this->mollie_config['test_mode'] ?? false;
    }

    public function getSuccessfulPaymentsCount(): int
    {
        return $this->payments()->where('status', 'completed')->count();
    }

    public function getTotalPaymentsAmount(): float
    {
        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    public function getPendingPaymentsCount(): int
    {
        return $this->payments()->where('status', 'pending')->count();
    }

    protected function generateSlug()
    {
        $slug = Str::slug($this->name);
        $originalSlug = $slug;
        $count = 1;

        while (
            static::withTrashed()
                ->where('slug', $slug)
                ->when($this->exists, fn($query) => $query->where('id', '!=', $this->id))
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $count++;
        }

        $this->slug = $slug;
    }

    public function generateApiKey(): string
    {
        do {
            $apiKey = 'pk_live_' . Str::random(32);
        } while (self::where('api_key', $apiKey)->exists());

        return $apiKey;
    }

    public function regenerateApiKey(): string
    {
        $this->api_key = $this->generateApiKey();
        $this->save();

        return $this->api_key;
    }

    public function getWidgetSettingsAttribute($value)
    {
        $defaults = [
            'colors' => [
                'primary' => '#e11d48',
                'secondary' => '#f9fafb',
                'text' => '#1f2937',
            ],
            'texts' => [
                'title' => 'Wähle deinen Tarif',
                'welcome_message' => 'Willkommen bei {gym_name}',
                'success_message' => 'Vielen Dank für deine Registrierung!',
            ],
            'features' => [
                'show_duration_selector' => true,
                'show_goals_selection' => true,
                'require_birth_date' => true,
                'require_phone' => true,
            ],
            'integrations' => [
                'google_recaptcha' => false,
                'sepa_mandate' => true,
            ],
        ];

        $settings = $value ? json_decode($value, true) : [];
        return array_merge($defaults, $settings);
    }

    public function getWidgetUrlAttribute()
    {
        return config('app.url') . '/embed/widget/' . $this->id;
    }

    public function getWidgetEmbedCodeAttribute()
    {
        return '<div id="gymportal-widget"></div>
<script>
(function() {
    const script = document.createElement("script");
    script.src = "' . config('app.url') . '/embed/widget.js";
    script.onload = function() {
        GymportalWidget.init({
            containerId: "gymportal-widget",
            apiEndpoint: "' . config('app.url') . '",
            apiKey: "' . $this->api_key . '",
            studioId: "' . $this->id . '"
        });
    };
    document.head.appendChild(script);
})();
</script>';
    }

    public function scopeWidgetEnabled($query)
    {
        return $query->where('widget_enabled', true);
    }
}
