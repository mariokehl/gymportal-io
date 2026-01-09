<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Gym extends Model
{
    const DEFAULT_ORGANIZATION_NAME = 'Mein Fitnessstudio';

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'slug',
        'description',
        'address',
        'city',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'phone',
        'email',
        'account_holder',
        'iban',
        'bic',
        'creditor_identifier',
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
        'api_key_generated_at',
        'trial_ends_at',
        'scanner_secret_key',

        // PWA Theming Fields
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'text_color',
        'pwa_logo_url',
        'favicon_url',
        'custom_css',
        'pwa_enabled',
        'pwa_settings',
        'opening_hours',
        'social_media',
        'member_app_description',
    ];

    protected $casts = [
        'subscription_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'payment_methods_config' => 'array',
        'widget_enabled' => 'boolean',
        'widget_settings' => 'array',
        'api_key_generated_at' => 'datetime',
        'pwa_enabled' => 'boolean',
        'pwa_settings' => 'array',
        'opening_hours' => 'array',
        'social_media' => 'array',
    ];

    protected $hidden = [
        'api_key',
        'scanner_secret_key',
    ];

    protected $appends = ['theme', 'pwa_manifest', 'logo_url'];

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

            // Default PWA settings
            if (empty($gym->pwa_settings)) {
                $gym->pwa_settings = $gym->getDefaultPwaSettings();
            }

            $gym->generateSlug();
        });

        static::updating(function ($gym) {
            if ($gym->isDirty('name')) {
                $gym->generateSlug();
            }
        });
    }

    // === EXISTING RELATIONSHIPS ===
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

    public function legalUrls()
    {
        return $this->hasMany(GymLegalUrl::class);
    }

    public function scanners()
    {
        return $this->hasMany(GymScanner::class);
    }

    public function accessLogs()
    {
        return $this->hasMany(ScannerAccessLog::class);
    }

    /**
     * Bestimmte Legal URL nach Typ abrufen
     */
    public function getLegalUrl(string $type): ?string
    {
        return $this->legalUrls()->where('type', $type)->value('url');
    }

    /**
     * Alle Legal URLs als assoziatives Array [type => url]
     */
    public function getLegalUrlsArray(): array
    {
        return $this->legalUrls()->pluck('url', 'type')->toArray();
    }

    // === NEW PWA THEMING METHODS ===

    /**
     * PWA Theme Attribute - für Frontend Consumption
     */
    protected function theme(): Attribute
    {
        return Attribute::make(
            get: function () {
                return [
                    'primary_color' => $this->primary_color,
                    'secondary_color' => $this->secondary_color,
                    'accent_color' => $this->accent_color,
                    'background_color' => $this->background_color ?: '#ffffff',
                    'text_color' => $this->text_color ?: '#1f2937',
                    'logo_url' => $this->getPwaLogoUrl(),
                    'favicon_url' => $this->favicon_url,
                    'custom_css' => $this->custom_css,
                ];
            }
        );
    }

    /**
     * PWA Manifest Attribute - Dynamic manifest generation
     */
    protected function pwaManifest(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Domain aus Request oder als Standard festlegen
                $domain = request()->header('Origin') ?: 'https://members.gymportal.io';

                return [
                    'name' => $this->name . ' - Mitglieder App',
                    'short_name' => $this->name,
                    'description' => $this->member_app_description ?: $this->description ?: "Mitglieder-App für {$this->name}",
                    'start_url' => $domain . '/' . $this->slug,
                    'display' => 'standalone',
                    'background_color' => $this->background_color ?: '#ffffff',
                    'theme_color' => $this->primary_color,
                    'orientation' => 'portrait-primary',
                    'scope' => $domain . '/',
                    'categories' => ['fitness', 'lifestyle', 'sports'],
                    'lang' => 'de',
                    'icons' => $this->getPwaIcons(),
                    'shortcuts' => $this->getPwaShortcuts($domain),
                ];
            }
        );
    }

    /**
     * Logo URL Attribute - for general logo display
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->logo_path) {
                    return null;
                }

                // If it's already a full URL, return as-is
                if (str_starts_with($this->logo_path, 'http')) {
                    return $this->logo_path;
                }

                // Return the storage URL
                return Storage::disk('public')->url($this->logo_path);
            }
        );
    }

    /**
     * Get PWA Logo URL with fallbacks
     */
    public function getPwaLogoUrl(): ?string
    {
        // Priority: PWA-specific logo > general logo > null
        if ($this->pwa_logo_url) {
            return $this->pwa_logo_url;
        }

        if ($this->logo_path) {
            // Convert relative path to full URL if needed
            if (str_starts_with($this->logo_path, 'http')) {
                return $this->logo_path;
            }
            return Storage::disk('public')->url($this->logo_path);
        }

        return null;
    }

    /**
     * Generate PWA Icons array for manifest
     */
    private function getPwaIcons(): array
    {
        $logoUrl = $this->getPwaLogoUrl();

        if (!$logoUrl) {
            // Fallback to default PWA icons
            return [
                [
                    'src' => '/pwa-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any'
                ],
                [
                    'src' => '/pwa-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ]
            ];
        }

        return [
            [
                'src' => $logoUrl,
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any'
            ],
            [
                'src' => $logoUrl,
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            ]
        ];
    }

    /**
     * Generate PWA Shortcuts for quick actions
     */
    private function getPwaShortcuts($domain): array
    {
        return [
            [
                'name' => 'QR-Code anzeigen',
                'short_name' => 'QR-Code',
                'description' => 'QR-Code für Zugangskontrolle anzeigen',
                'url' => "{$domain}/{$this->slug}/qr-code",
                'icons' => [
                    [
                        'src' => '/icons/qr-icon.png',
                        'sizes' => '96x96'
                    ]
                ]
            ],
            [
                'name' => 'Profil bearbeiten',
                'short_name' => 'Profil',
                'description' => 'Persönliche Daten verwalten',
                'url' => "{$domain}/{$this->slug}/profile",
                'icons' => [
                    [
                        'src' => '/icons/profile-icon.png',
                        'sizes' => '96x96'
                    ]
                ]
            ]
        ];
    }

    /**
     * Default PWA Settings
     */
    private function getDefaultPwaSettings(): array
    {
        return [
            'install_prompt_enabled' => true,
            'offline_support_enabled' => true,
            'push_notifications_enabled' => false,
            'background_sync_enabled' => true,
            'cache_strategy' => 'network_first',
            'cache_duration_hours' => 24,
        ];
    }

    /**
     * Check if PWA features are available
     */
    public function isPwaEnabled(): bool
    {
        return $this->pwa_enabled && $this->canAccessPremiumFeatures();
    }

    /**
     * Get opening hours in a formatted way for display
     */
    public function getFormattedOpeningHours(): array
    {
        if (!$this->opening_hours) {
            return [];
        }

        $days = [
            'monday' => 'Montag',
            'tuesday' => 'Dienstag',
            'wednesday' => 'Mittwoch',
            'thursday' => 'Donnerstag',
            'friday' => 'Freitag',
            'saturday' => 'Samstag',
            'sunday' => 'Sonntag'
        ];

        $formatted = [];
        foreach ($days as $key => $name) {
            if (isset($this->opening_hours[$key])) {
                $hours = $this->opening_hours[$key];
                $formatted[] = [
                    'day' => $name,
                    'open' => $hours['open'] ?? null,
                    'close' => $hours['close'] ?? null,
                    'closed' => $hours['closed'] ?? false,
                ];
            }
        }

        return $formatted;
    }

    /**
     * Update PWA theme colors
     */
    public function updateThemeColors(array $colors): bool
    {
        $validColors = ['primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color'];
        $updateData = [];

        foreach ($colors as $key => $value) {
            if (in_array($key, $validColors) && $this->isValidHexColor($value)) {
                $updateData[$key] = $value;
            }
        }

        if (empty($updateData)) {
            return false;
        }

        return $this->update($updateData);
    }

    /**
     * Validate hex color format
     */
    public static function isValidHexColor($color): bool
    {
        return preg_match('/^#[a-f0-9]{6}$/i', $color);
    }

    /**
     * Get member app API data (for PWA consumption)
     */
    public function getMemberAppData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->member_app_description ?: $this->description,
            'logo' => $this->logo_path,
            'logo_url' => $this->getPwaLogoUrl(),
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'website' => $this->website,
            'opening_hours' => $this->opening_hours,
            'social_media' => $this->social_media,
            'theme' => $this->theme,
            'pwa_enabled' => $this->isPwaEnabled(),
            'legal_urls' => $this->getLegalUrlsArray(),
        ];
    }

    protected function getDefaultPaymentMethodsConfig(): array
    {
        return PaymentMethod::getDefaultConfig();
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
                'requires_mandate' => $this->getMollieMandateType($methodId) ? true : false,
                'mollie_method_id' => $methodId,
            ];
        }

        return $methods;
    }

    public function getMollieMandateType(string $methodId): string
    {
        if ($methodId === 'directdebit') return 'directdebit';

        $mandateTypes = [
            'creditcard' => 'creditcard',
            'paypal' => 'paypal',
            'belfius' => 'directdebit',
            'bancontact' => 'directdebit',
            'eps' => 'directdebit',
            'ideal' => 'directdebit',
            'kbc' => 'directdebit',
            'paybybank' => 'directdebit',
            'trustly' => 'directdebit'
        ];

        return $mandateTypes[$methodId] ?? '';
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
        return array_values(array_filter($this->getAllPaymentMethods(), function ($method) {
            return $method['enabled'];
        }));
    }

    protected function isPaymentMethodOverriddenByIntegration(string $methodKey): bool
    {
        if ($this->hasMollieConfigured()) {
            $mollieMethodIds = $this->getMollieEnabledMethods();

            switch ($methodKey) {
                case 'banktransfer':
                    return !empty(array_intersect($mollieMethodIds, ['banktransfer', 'ideal', 'mybank', 'trustly']));

                case 'sepa_direct_debit':
                    return in_array('directdebit', $mollieMethodIds);

                case 'cash':
                    return in_array('pointofsale', $mollieMethodIds);

                case 'invoice':
                    return !empty(array_intersect($mollieMethodIds, ['billie', 'klarna', 'riverty', 'in3']));

                case 'standingorder':
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

    public function getPaymentMethodForKey(string $key): array|bool
    {
        $enabledMethods = $this->getEnabledPaymentMethods();

        foreach ($enabledMethods as $method) {
            if ($method['key'] === $key) {
                return $method;
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
               $this->subscription_ends_at->gt(now()->subHours(2));
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
        if ($value === null) {
            $this->attributes['mollie_config'] = null;
            return;
        }

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
        return $this->payments()->where('status', 'paid')->count();
    }

    public function getTotalPaymentsAmount(): float
    {
        return $this->payments()->where('status', 'paid')->sum('amount');
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
        $this->api_key_generated_at = now();
        $this->save();

        return $this->api_key;
    }

    public function getWidgetSettingsAttribute($value)
    {
        $defaults = [
            'colors' => [
                'primary' => $this->primary_color, // Use gym's primary color as default
                'secondary' => '#f9fafb',
                'text' => $this->text_color ?: '#1f2937',
            ],
            'texts' => [
                'title' => 'Wähle deinen Tarif',
                'welcome_message' => 'Willkommen bei {gym_name}',
                'success_message' => 'Vielen Dank für deine Registrierung!',
            ],
            'features' => [
                'show_duration_selector' => false,
                'show_goals_selection' => false,
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

    public function generateScannerSecretKey(): void
    {
        $this->scanner_secret_key = base64_encode(random_bytes(32));
        $this->save();
    }

    public function getCurrentScannerKey(): ?string
    {
        return $this->scanner_secret_key;
    }

    public function validateHash(string $memberId, string $timestamp, string $providedHash): bool
    {
        if ($this->checkHashWithKey($memberId, $timestamp, $providedHash, $this->scanner_secret_key)) {
            return true;
        }

        return false;
    }

    private function checkHashWithKey($memberId, $timestamp, $providedHash, $secretKey): bool
    {
        $message = "{$memberId}:{$timestamp}";
        $expectedHash = hash_hmac('sha256', $message, $secretKey);
        return hash_equals($expectedHash, $providedHash);
    }

    public function scopeWidgetEnabled($query)
    {
        return $query->where('widget_enabled', true);
    }

    public function scopePwaEnabled($query)
    {
        return $query->where('pwa_enabled', true);
    }

    /**
     * Get the display name for the gym
     * Returns display_name if set, otherwise falls back to name
     */
    public function getDisplayName(): string
    {
        return $this->display_name ?: $this->name;
    }
}
