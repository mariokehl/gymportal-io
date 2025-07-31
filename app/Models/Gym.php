<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

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
        'payment_methods_config',
        'api_key',
        'widget_enabled',
        'widget_settings',
        'trial_ends_at',
    ];

    protected $casts = [
        'subscription_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'widget_settings' => 'array',
        'payment_methods_config' => 'array',
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

            if (empty($gym->trial_ends_at)) {
                $gym->trial_ends_at = now()->addDays(30);
            }

            if (empty($gym->payment_methods_config)) {
                $gym->payment_methods_config = $gym->getDefaultPaymentMethodsConfig();
            }

            $gym->generateSlug();
        });

        static::updating(function ($gym) {
            if ($gym->isDirty('name')) {
                $gym->generateSlug();
            }
        });
    }

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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices()
    {
        //return $this->hasMany(Invoice::class);
    }

    protected function getDefaultPaymentMethodsConfig(): array
    {
        return [
            'banktransfer' => [
                'enabled' => false,
                'name' => 'Manuelle Überweisung (Vorkasse)',
                'description' => 'Mitglied überweist selbst per IBAN',
                'icon' => 'Wallet',
            ],
            'cash' => [
                'enabled' => false,
                'name' => 'Barzahlung',
                'description' => 'Zahlung erfolgt vor Ort in bar',
                'icon' => 'HandCoins',
            ],
            'invoice' => [
                'enabled' => false,
                'name' => 'Zahlung auf Rechnung',
                'description' => 'Mitglied erhält eine Rechnung zur Überweisung',
                'icon' => 'FileText',
            ],
            'standingorder' => [
                'enabled' => false,
                'name' => 'Dauerauftrag',
                'description' => 'Wiederkehrende Überweisung durch Mitglied',
                'icon' => 'DollarSign',
            ],
            'sepa_direct_debit' => [
                'enabled' => false,
                'name' => 'SEPA-Lastschrift',
                'description' => 'Automatischer Einzug vom Bankkonto',
                'icon' => 'CreditCard',
                'requires_mandate' => true,
            ],
        ];
    }

    public function getStandardPaymentMethods(): array
    {
        $config = $this->payment_methods_config ?? $this->getDefaultPaymentMethodsConfig();
        $methods = [];

        foreach ($config as $key => $method) {
            $methods[] = array_merge($method, [
                'key' => $key,
                'type' => 'standard',
                'is_overridden' => $this->isPaymentMethodOverriddenByIntegration($key),
            ]);
        }

        return $methods;
    }

    public function getEnabledStandardPaymentMethods(): array
    {
        return array_filter($this->getStandardPaymentMethods(), function ($method) {
            return $method['enabled'] && !$method['is_overridden'];
        });
    }

    public function getMolliePaymentMethods(): array
    {
        if (!$this->hasMollieConfigured()) {
            return [];
        }

        $methods = [];
        $enabledMethods = $this->getMollieEnabledMethods();

        foreach ($enabledMethods as $methodId) {
            $methods[] = [
                'key' => 'mollie_' . $methodId,
                'name' => $this->getMollieMethodDisplayName($methodId, ''),
                'description' => 'Via Mollie' . ($this->isInTestMode() ? ' (Test-Modus)' : ''),
                'icon' => 'CreditCard',
                'type' => 'mollie',
                'enabled' => true,
                'mollie_method_id' => $methodId,
            ];
        }

        return $methods;
    }

    protected function getMollieMethodDisplayName(string $methodId, string $fallbackDescription): string
    {
        $displayNames = [
            'alma' => 'Alma (Buy Now, Pay Later)',
            'applepay' => 'Apple Pay',
            'bacs' => 'BACS Direct Debit (UK)',
            'bancomatpay' => 'Bancomat Pay',
            'bancontact' => 'Bancontact',
            'banktransfer' => 'Banküberweisung (Mollie)',
            'belfius' => 'Belfius Pay Button',
            'billie' => 'Billie (Rechnungskauf)',
            'blik' => 'BLIK',
            'creditcard' => 'Kreditkarte',
            'directdebit' => 'SEPA-Lastschrift (Mollie)',
            'eps' => 'EPS',
            'giftcard' => 'Geschenkkarte',
            'ideal' => 'iDEAL',
            'in3' => 'in3 (Buy Now, Pay Later)',
            'kbc' => 'KBC/CBC Payment Button',
            'klarna' => 'Klarna',
            'mbway' => 'MB WAY',
            'multibanco' => 'Multibanco',
            'mybank' => 'MyBank',
            'payconiq' => 'Payconiq',
            'paypal' => 'PayPal',
            'paysafecard' => 'paysafecard',
            'pointofsale' => 'Point of Sale (Terminal)',
            'przelewy24' => 'Przelewy24',
            'riverty' => 'Riverty (ehemals AfterPay)',
            'satispay' => 'Satispay',
            'swish' => 'Swish',
            'trustly' => 'Trustly',
            'twint' => 'TWINT',
            'voucher' => 'Voucher',
        ];

        return $displayNames[$methodId] ?? $fallbackDescription;
    }

    public function getAllPaymentMethods(): array
    {
        $methods = [];
        $methods = array_merge($methods, $this->getStandardPaymentMethods());
        $methods = array_merge($methods, $this->getMolliePaymentMethods());

        return $methods;
    }

    public function getEnabledPaymentMethods(): array
    {
        return array_filter($this->getAllPaymentMethods(), function ($method) {
            return $method['enabled'];
        });
    }

    protected function isPaymentMethodOverriddenByIntegration(string $methodKey): bool
    {
        if ($this->hasMollieConfigured()) {
            $mollieMethodIds = $this->getMollieEnabledMethods();

            switch ($methodKey) {
                case 'banktransfer':
                    // Mollie banktransfer, ideal, mybank, trustly overwrite manuelle Überweisung
                    return !empty(array_intersect($mollieMethodIds, ['banktransfer', 'ideal', 'mybank', 'trustly']));

                case 'sepa_direct_debit':
                    // Mollie directdebit overwrites SEPA-Lastschrift
                    return in_array('directdebit', $mollieMethodIds);

                case 'cash':
                    // Mollie pointofsale overwrites Barzahlung
                    return in_array('pointofsale', $mollieMethodIds);

                case 'invoice':
                    // Mollie billie, klarna, riverty, in3 overwrite Rechnung
                    return !empty(array_intersect($mollieMethodIds, ['billie', 'klarna', 'riverty', 'in3']));

                case 'standingorder':
                    // Mollie directdebit can replace Dauerauftrag
                    return in_array('directdebit', $mollieMethodIds);
            }
        }

        return false;
    }

    public function updateStandardPaymentMethod(string $methodKey, bool $enabled): bool
    {
        $config = $this->payment_methods_config ?? $this->getDefaultPaymentMethodsConfig();

        if (!isset($config[$methodKey])) {
            return false;
        }

        $config[$methodKey]['enabled'] = $enabled;
        $this->payment_methods_config = $config;
        $this->save();

        return true;
    }

    public function hasPaymentMethodForType(string $type): bool
    {
        $enabledMethods = $this->getEnabledPaymentMethods();

        foreach ($enabledMethods as $method) {
            if ($method['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    public function requiresSepaMandate(): bool
    {
        $enabledMethods = $this->getEnabledPaymentMethods();

        foreach ($enabledMethods as $method) {
            if (isset($method['requires_mandate']) && $method['requires_mandate']) {
                return true;
            }
        }

        return false;
    }

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

    public function setMollieConfigAttribute($value)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $this->attributes['mollie_config'] = Crypt::encryptString($value);
    }

    public function getMollieConfigAttribute($value)
    {
        try {
            return json_decode(Crypt::decryptString($value), true);
        } catch (\Exception $e) {
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
