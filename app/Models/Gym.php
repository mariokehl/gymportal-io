<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Gym extends Model
{
    const DEFAULT_ORGANIZATION_NAME = 'Mein Fitnessstudio';

    /**
     * Only members registered at this location may check in. Matches the
     * behaviour of the system before cross-location check-in existed.
     */
    public const CHECKIN_RULE_OWN = 'own';

    /**
     * Members of this location plus the locations listed in
     * allowedCheckinGyms() may check in.
     */
    public const CHECKIN_RULE_SELECTED = 'selected';

    /**
     * Every member of the organisation may check in here.
     */
    public const CHECKIN_RULE_ALL = 'all';

    public const CHECKIN_RULES = [
        self::CHECKIN_RULE_OWN,
        self::CHECKIN_RULE_SELECTED,
        self::CHECKIN_RULE_ALL,
    ];

    /**
     * The organisation symbol falls back to the first letter of the name.
     */
    public const SYMBOL_TYPE_INITIAL = 'initial';

    /**
     * The organisation symbol is a single emoji picked by the operator.
     */
    public const SYMBOL_TYPE_EMOJI = 'emoji';

    public const SYMBOL_TYPES = [
        self::SYMBOL_TYPE_INITIAL,
        self::SYMBOL_TYPE_EMOJI,
    ];

    /**
     * Used whenever an organisation has no colour of its own. Matches the
     * indigo the rest of the backend uses for primary actions.
     */
    public const DEFAULT_SYMBOL_COLOR = '#4f46e5';

    /**
     * The colours offered in the settings form. Mirrors SYMBOL_COLORS in
     * resources/js/utils/organizationSymbol.js.
     */
    public const SYMBOL_COLORS = [
        '#4f46e5', '#2563eb', '#0891b2', '#059669',
        '#65a30d', '#d97706', '#ea580c', '#dc2626',
        '#db2777', '#7c3aed', '#475569', '#111827',
    ];

    /**
     * The emojis offered in the settings form. Mirrors SYMBOL_EMOJIS in
     * resources/js/utils/organizationSymbol.js.
     */
    public const SYMBOL_EMOJIS = [
        '🏋️', '💪', '🤸', '🥊', '🧘', '🏃', '🚴', '🏊',
        '⚽', '🏆', '🥇', '🎯', '🔥', '⚡', '⭐', '🌊',
        '🌲', '🍀', '🏙️', '🏢', '📍', '🧭', '🛡️', '🎽',
    ];

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'symbol_type',
        'symbol_emoji',
        'symbol_color',
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
        'payment_execution_settings',
        'api_key',
        'widget_enabled',
        'widget_settings',
        'contracts_start_first_of_month',
        'free_trial_membership_name',
        'api_key_generated_at',
        'trial_ends_at',
        'scanner_secret_key',
        'cross_location_checkin_rule',
        'checkin_station_enabled',
        'checkin_station_token',
        'rolling_qr_enabled',
        'rolling_qr_interval',
        'rolling_qr_tolerance_windows',

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
        'contract_settings',
        'inkasso_settings',
    ];

    protected $casts = [
        'subscription_ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'payment_methods_config' => 'array',
        'payment_execution_settings' => 'array',
        'widget_enabled' => 'boolean',
        'widget_settings' => 'array',
        'contracts_start_first_of_month' => 'boolean',
        'checkin_station_enabled' => 'boolean',
        'rolling_qr_enabled' => 'boolean',
        'api_key_generated_at' => 'datetime',
        'pwa_enabled' => 'boolean',
        'pwa_settings' => 'array',
        'opening_hours' => 'array',
        'social_media' => 'array',
        'contract_settings' => 'array',
        'inkasso_settings' => 'array',
    ];

    protected $hidden = [
        'api_key',
        'scanner_secret_key',
        'checkin_station_token',
        // Contains the encrypted partner password; the frontend gets the
        // sanitised version through the appended `inkasso` attribute instead.
        'inkasso_settings',
    ];

    protected $appends = ['theme', 'pwa_manifest', 'logo_url', 'inkasso'];

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
        return $this->belongsToMany(User::class, 'gym_users')->withPivot('role')->withTimestamps();
    }

    public function staff()
    {
        return $this->belongsToMany(User::class, 'gym_users')->wherePivot('role', 'staff');
    }

    public function trainers()
    {
        return $this->belongsToMany(User::class, 'gym_users')->wherePivot('role', 'trainer');
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function invitations()
    {
        return $this->hasMany(GymInvitation::class);
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

    public function googleSheetIntegration()
    {
        return $this->hasOne(GymGoogleSheetIntegration::class);
    }

    /**
     * Locations whose members this gym accepts while its rule is 'selected'.
     *
     * Directed: an entry here means "we let their members in", it says nothing
     * about the other direction.
     */
    public function allowedCheckinGyms()
    {
        return $this->belongsToMany(
            self::class,
            'gym_allowed_checkin_gyms',
            'gym_id',
            'allowed_gym_id'
        )->withTimestamps();
    }

    /**
     * All locations of the organisation this gym belongs to.
     *
     * The organisation is implied by the owner — every gym owned by the same
     * user forms one organisation. Returns at least this gym itself.
     */
    public function organizationGyms()
    {
        return self::query()
            ->where('owner_id', $this->owner_id)
            ->orderBy('name');
    }

    /**
     * The effective check-in rule, defaulting to the closed 'own'.
     *
     * The column is non-null in the database, but a model may still carry null
     * here (factory-built, partial select), and that must read as closed rather
     * than falling through to a permissive branch.
     */
    public function checkinRule(): string
    {
        return $this->cross_location_checkin_rule ?? self::CHECKIN_RULE_OWN;
    }

    /**
     * Whether this location lets a member whose home location is $homeGymId
     * check in. Answers only the location half of the decision — the member's
     * contract has to allow it as well, see CrossLocationAccessService.
     */
    public function acceptsMembersFrom(int $homeGymId): bool
    {
        if ($homeGymId === $this->id) {
            return true;
        }

        // A foreign location is only ever reachable inside the same organisation.
        // A gym without an owner belongs to no organisation at all — guard it,
        // or every ownerless gym would match every other one.
        if (! $this->owner_id) {
            return false;
        }

        if (! self::whereKey($homeGymId)->where('owner_id', $this->owner_id)->exists()) {
            return false;
        }

        return match ($this->checkinRule()) {
            self::CHECKIN_RULE_ALL => true,
            self::CHECKIN_RULE_SELECTED => $this->allowedCheckinGyms()
                ->where('allowed_gym_id', $homeGymId)
                ->exists(),
            default => false,
        };
    }

    /**
     * Whether the printed check-in station is usable at this location.
     *
     * Enabled alone is not enough — without a token there is nothing for a
     * member to scan, and a half-configured gym must read as closed.
     */
    public function hasCheckinStation(): bool
    {
        return (bool) $this->checkin_station_enabled && ! empty($this->checkin_station_token);
    }

    /**
     * Issue a station token, replacing any existing one.
     *
     * Rotating invalidates every printed sheet in circulation, which is exactly
     * what an operator wants after a code leaks — so the caller has to mean it.
     */
    public function rotateCheckinStationToken(): string
    {
        $token = Str::random(48);

        $this->forceFill(['checkin_station_token' => $token])->save();

        return $token;
    }

    /**
     * Drop the station token entirely.
     *
     * Called when the operator switches the station off: a disabled station has
     * no reason to keep a working code on file, and leaving one behind would
     * mean a leaked sheet quietly regains access the moment the feature is
     * switched back on. Re-enabling therefore mints a fresh token and every
     * previously printed sheet stays dead.
     */
    public function clearCheckinStationToken(): void
    {
        $this->forceFill(['checkin_station_token' => null])->save();
    }

    /**
     * Constant-time comparison of a scanned token against this gym's.
     *
     * Reading the column directly rather than through the hidden-attribute
     * accessor, and comparing with hash_equals so a wrong token cannot be
     * narrowed down by timing.
     */
    public function matchesCheckinStationToken(string $token): bool
    {
        $expected = $this->checkin_station_token;

        return is_string($expected) && $expected !== '' && hash_equals($expected, $token);
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
                $domain = request()->header('Origin') ?: config('app.pwa_url');

                return [
                    'name' => $this->name.' - Mitglieder App',
                    'short_name' => $this->name,
                    'description' => $this->member_app_description ?: $this->description ?: "Mitglieder-App für {$this->name}",
                    'start_url' => $domain.'/'.$this->slug,
                    'display' => 'standalone',
                    'background_color' => $this->background_color ?: '#ffffff',
                    'theme_color' => $this->primary_color,
                    'orientation' => 'portrait-primary',
                    'scope' => $domain.'/',
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
                if (! $this->logo_path) {
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

        if (! $logoUrl) {
            // Fallback to default PWA icons
            return [
                [
                    'src' => '/pwa-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/pwa-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ];
        }

        return [
            [
                'src' => $logoUrl,
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => $logoUrl,
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ],
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
                        'sizes' => '96x96',
                    ],
                ],
            ],
            [
                'name' => 'Profil bearbeiten',
                'short_name' => 'Profil',
                'description' => 'Persönliche Daten verwalten',
                'url' => "{$domain}/{$this->slug}/profile",
                'icons' => [
                    [
                        'src' => '/icons/profile-icon.png',
                        'sizes' => '96x96',
                    ],
                ],
            ],
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
            'pwa_login_disabled' => false,
            'app_store_url_ios' => null,
            'app_store_url_android' => null,
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
     * Check if PWA login is disabled (only affects PWA, not native app)
     */
    public function isPwaLoginDisabled(): bool
    {
        return (bool) ($this->pwa_settings['pwa_login_disabled'] ?? false);
    }

    /**
     * Get app store links for native app badges
     */
    public function getAppStoreLinks(): array
    {
        return array_filter([
            'ios' => $this->pwa_settings['app_store_url_ios'] ?? null,
            'android' => $this->pwa_settings['app_store_url_android'] ?? null,
        ]);
    }

    /**
     * Get opening hours in a formatted way for display
     */
    public function getFormattedOpeningHours(): array
    {
        if (! $this->opening_hours) {
            return [];
        }

        $days = [
            'monday' => 'Montag',
            'tuesday' => 'Dienstag',
            'wednesday' => 'Mittwoch',
            'thursday' => 'Donnerstag',
            'friday' => 'Freitag',
            'saturday' => 'Samstag',
            'sunday' => 'Sonntag',
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
            'rolling_qr_enabled' => $this->rolling_qr_enabled,
            'rolling_qr_interval' => $this->rolling_qr_interval ?? 3,
            'pwa_login_disabled' => $this->isPwaLoginDisabled(),
            'app_store_links' => $this->getAppStoreLinks(),
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
            return $method['enabled'] && ! $method['is_overridden'];
        });
    }

    public function getMolliePaymentMethods(): array
    {
        if (! $this->hasMollieConfigured()) {
            return [];
        }

        $methods = [];
        $enabledMethods = $this->getMollieEnabledMethods();

        foreach ($enabledMethods as $methodId) {
            $methods[] = [
                'key' => 'mollie_'.$methodId,
                'name' => $this->getMollieMethodDisplayName($methodId, ''),
                'description' => 'Via Mollie'.($this->isInTestMode() ? ' (Test-Modus)' : ''),
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
        if ($methodId === 'directdebit') {
            return 'directdebit';
        }

        $mandateTypes = [
            'creditcard' => 'creditcard',
            'paypal' => 'paypal',
            'belfius' => 'directdebit',
            'bancontact' => 'directdebit',
            'eps' => 'directdebit',
            'ideal' => 'directdebit',
            'kbc' => 'directdebit',
            'paybybank' => 'directdebit',
            'trustly' => 'directdebit',
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

    /**
     * The payment method key that actually applies for a standard method,
     * which is the integration's own key once that integration has taken the
     * method over — e.g. SEPA direct debit collected through Mollie.
     */
    public function resolvePaymentMethodKey(string $methodKey): string
    {
        if (! $this->isPaymentMethodOverriddenByIntegration($methodKey)) {
            return $methodKey;
        }

        return match ($methodKey) {
            'sepa_direct_debit', 'standingorder' => 'mollie_directdebit',
            default => $methodKey,
        };
    }

    protected function isPaymentMethodOverriddenByIntegration(string $methodKey): bool
    {
        if ($this->hasMollieConfigured()) {
            $mollieMethodIds = $this->getMollieEnabledMethods();

            switch ($methodKey) {
                case 'banktransfer':
                    return ! empty(array_intersect($mollieMethodIds, ['banktransfer', 'ideal', 'mybank', 'trustly']));

                case 'sepa_direct_debit':
                    return in_array('directdebit', $mollieMethodIds);

                case 'cash':
                    return in_array('pointofsale', $mollieMethodIds);

                case 'invoice':
                    return ! empty(array_intersect($mollieMethodIds, ['billie', 'klarna', 'riverty', 'in3']));

                case 'standingorder':
                    return in_array('directdebit', $mollieMethodIds);
            }
        }

        return false;
    }

    /**
     * Resolve the execution offsets for a payment method type, in days relative
     * to the due date. A gym-specific override wins over the system default;
     * without one the system default applies unchanged.
     *
     * @return array{initial: int, recurring: int}
     */
    public function getPaymentExecutionOffsets(?string $methodKey): array
    {
        $defaults = PaymentMethod::getDefaultExecutionOffsets($methodKey);

        if ($methodKey === null) {
            return $defaults;
        }

        $override = $this->payment_execution_settings[$methodKey] ?? null;

        if (! is_array($override)) {
            return $defaults;
        }

        return [
            'initial' => PaymentMethod::clampExecutionOffset(
                (int) ($override['initial'] ?? $defaults['initial'])
            ),
            'recurring' => PaymentMethod::clampExecutionOffset(
                (int) ($override['recurring'] ?? $defaults['recurring'])
            ),
        ];
    }

    /**
     * Offset in days applied to the first payment of a membership.
     */
    public function getInitialPaymentExecutionOffset(?string $methodKey): int
    {
        return $this->getPaymentExecutionOffsets($methodKey)['initial'];
    }

    /**
     * Offset in days applied to every recurring payment.
     */
    public function getRecurringPaymentExecutionOffset(?string $methodKey): int
    {
        return $this->getPaymentExecutionOffsets($methodKey)['recurring'];
    }

    /**
     * Whether the gym overrides the system defaults for a payment method.
     */
    public function hasCustomPaymentExecutionOffsets(string $methodKey): bool
    {
        return is_array($this->payment_execution_settings[$methodKey] ?? null);
    }

    /**
     * Store a gym-specific override for a payment method. Offsets are clamped
     * into the supported range before they are persisted.
     */
    public function setPaymentExecutionOffsets(string $methodKey, int $initial, int $recurring): void
    {
        $settings = $this->payment_execution_settings ?? [];

        $settings[$methodKey] = [
            'initial' => PaymentMethod::clampExecutionOffset($initial),
            'recurring' => PaymentMethod::clampExecutionOffset($recurring),
        ];

        $this->payment_execution_settings = $settings;
        $this->save();
    }

    /**
     * Drop a gym-specific override so the system defaults apply again.
     */
    public function resetPaymentExecutionOffsets(string $methodKey): void
    {
        $settings = $this->payment_execution_settings ?? [];

        unset($settings[$methodKey]);

        $this->payment_execution_settings = $settings;
        $this->save();
    }

    /**
     * Drop every gym-specific override at once.
     */
    public function resetAllPaymentExecutionOffsets(): void
    {
        $this->payment_execution_settings = [];
        $this->save();
    }

    /**
     * Execution date configuration for the settings UI: one entry per enabled
     * payment method, carrying the effective offsets, the system defaults and
     * whether the gym deviates from them.
     */
    public function getPaymentExecutionSettingsOverview(): array
    {
        return array_map(function (array $method) {
            $defaults = PaymentMethod::getDefaultExecutionOffsets($method['key']);
            $offsets = $this->getPaymentExecutionOffsets($method['key']);

            return [
                'key' => $method['key'],
                'name' => $method['name'],
                'type' => $method['type'],
                'is_custom' => $this->hasCustomPaymentExecutionOffsets($method['key']),
                'initial' => $offsets['initial'],
                'recurring' => $offsets['recurring'],
                'default_initial' => $defaults['initial'],
                'default_recurring' => $defaults['recurring'],
            ];
        }, $this->getEnabledPaymentMethods());
    }

    public function updateStandardPaymentMethod(string $methodKey, bool $enabled): bool
    {
        $config = $this->payment_methods_config ?? $this->getDefaultPaymentMethodsConfig();

        if (! isset($config[$methodKey])) {
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
        if (! $this->isInTrial()) {
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
            'trial_ends_at' => now()->addDays($days),
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
        return ! empty($this->mollie_config) &&
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
                ->when($this->exists, fn ($query) => $query->where('id', '!=', $this->id))
                ->exists()
        ) {
            $slug = $originalSlug.'-'.$count++;
        }

        $this->slug = $slug;
    }

    public function generateApiKey(): string
    {
        do {
            $apiKey = 'pk_live_'.Str::random(32);
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
                'min_age' => 18,
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
        return config('app.url').'/embed/widget/'.$this->id;
    }

    public function getWidgetEmbedCodeAttribute()
    {
        return '<div id="gymportal-widget"></div>
<script>
(function() {
    const script = document.createElement("script");
    script.src = "'.config('app.url').'/embed/widget.js";
    script.onload = function() {
        GymportalWidget.init({
            containerId: "gymportal-widget",
            apiEndpoint: "'.config('app.url').'",
            apiKey: "'.$this->api_key.'",
            studioId: "'.$this->id.'"
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

        // A visiting member's code is signed with their home location's key, so
        // fall back to the keys of the locations this one accepts. Whether the
        // member may actually enter is a separate decision — a valid signature
        // only proves the code is genuine, see CrossLocationAccessService.
        foreach ($this->acceptedCheckinKeys() as $key) {
            if ($this->checkHashWithKey($memberId, $timestamp, $providedHash, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scanner keys of the locations whose members this gym accepts.
     *
     * Handed to the devices in their config so they can verify a visiting
     * member's QR code offline. Empty while the rule is 'own', which keeps
     * every existing installation exactly as it is today.
     *
     * @return array<string>
     */
    public function acceptedCheckinKeys(): array
    {
        // Anything but an explicit opt-in means no foreign key is handed out —
        // a model built without the column (factory, partial select) reads null
        // here, and null must never be treated as "open".
        if ($this->checkinRule() !== self::CHECKIN_RULE_SELECTED
            && $this->checkinRule() !== self::CHECKIN_RULE_ALL) {
            return [];
        }

        $query = $this->organizationGyms()->where('gyms.id', '!=', $this->id);

        if ($this->checkinRule() === self::CHECKIN_RULE_SELECTED) {
            $query->whereIn('gyms.id', $this->allowedCheckinGyms()->pluck('gyms.id'));
        }

        return $query->pluck('scanner_secret_key')
            ->filter()
            ->values()
            ->all();
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

    /**
     * The symbol shown in the sidebar and the organization switcher.
     *
     * Returns the emoji when the operator picked one, otherwise the first
     * letter of the display name. The colour always resolves to a value so
     * the frontend never has to repeat the fallback.
     *
     * @return array{type: string, emoji: string|null, color: string, initial: string}
     */
    public function getSymbol(): array
    {
        $type = in_array($this->symbol_type, self::SYMBOL_TYPES, true)
            ? $this->symbol_type
            : self::SYMBOL_TYPE_INITIAL;

        $emoji = $this->symbol_emoji !== null && $this->symbol_emoji !== ''
            ? $this->symbol_emoji
            : null;

        // An emoji symbol without an emoji would render an empty tile.
        if ($type === self::SYMBOL_TYPE_EMOJI && $emoji === null) {
            $type = self::SYMBOL_TYPE_INITIAL;
        }

        return [
            'type' => $type,
            'emoji' => $emoji,
            'color' => $this->symbol_color ?: self::DEFAULT_SYMBOL_COLOR,
            'initial' => Str::upper(Str::substr(trim($this->getDisplayName()), 0, 1)),
        ];
    }

    // === CONTRACT SETTINGS ===

    public function getContractSettingsAttribute($value): array
    {
        $defaults = [
            'signing_method' => 'offline',
            'contract_template_body' => null,
            'contract_template_subject' => 'Mitgliedschaftsvertrag',
        ];

        $settings = $value ? json_decode($value, true) : [];

        return array_merge($defaults, $settings);
    }

    public function isOnlineContractEnabled(): bool
    {
        return ($this->contract_settings['signing_method'] ?? 'offline') === 'online';
    }

    // === INKASSO SETTINGS ===

    /** Remaining claims are always written off once the partner closes a case. */
    public const RESIDUAL_ALWAYS_WRITE_OFF = 'always_write_off';

    /** Remaining claims are only written off when the partner says so. */
    public const RESIDUAL_PARTNER_DECISION = 'partner_decision';

    /**
     * Days a member is given to pay, used when a level carries no own value.
     */
    public const DEFAULT_PAYMENT_PERIOD_DAYS = 14;

    /**
     * Default dunning levels. Level 4 is the handover to the collection partner
     * and is never triggered automatically.
     *
     * `trigger_days` is the waiting time until the level is reached,
     * `payment_period_days` the deadline printed in the notice sent for it.
     */
    public const DEFAULT_DUNNING_LEVELS = [
        ['level' => 1, 'trigger_days' => 7, 'payment_period_days' => 14, 'fee' => 0.0, 'effect' => 'Zahlungserinnerung per E-Mail'],
        ['level' => 2, 'trigger_days' => 14, 'payment_period_days' => 14, 'fee' => 5.0, 'effect' => '1. Mahnung, Gebühr wird als Forderung gebucht'],
        ['level' => 3, 'trigger_days' => 14, 'payment_period_days' => 10, 'fee' => 10.0, 'effect' => '2. Mahnung, Mitglied wird „Bereit für Inkasso“'],
        ['level' => 4, 'trigger_days' => null, 'payment_period_days' => null, 'fee' => 0.0, 'effect' => 'Übergabe an den Inkassopartner, Zugangssperre'],
    ];

    public function getInkassoSettingsAttribute($value): array
    {
        $defaults = [
            'active' => false,
            'partner' => 'diagonal',
            'tenant_id' => null,
            // Five character creditor number issued by DIAGONAL (ClientDataItem.clientNumber).
            'client_number' => null,
            'username' => null,
            'password' => null,
            // Route the API calls to the DIAGONAL test host instead of production.
            'sandbox' => false,
            'creditor_name' => null,
            'contact' => null,
            'min_amount' => 10.0,
            'include_minors' => false,
            'residual_handling' => self::RESIDUAL_ALWAYS_WRITE_OFF,
            'auto_resubmit' => true,
            'handover_flat_fee' => 0,
            'default_interest_rate' => 5.0,
            'activated_at' => null,
            'levels' => self::DEFAULT_DUNNING_LEVELS,
        ];

        $settings = $value ? json_decode($value, true) : [];

        return array_merge($defaults, is_array($settings) ? $settings : []);
    }

    public function isInkassoEnabled(): bool
    {
        return (bool) ($this->inkasso_settings['active'] ?? false);
    }

    /**
     * Whether the partner API calls of this gym go to the test environment.
     */
    public function usesInkassoSandbox(): bool
    {
        return (bool) ($this->inkasso_settings['sandbox'] ?? false);
    }

    /**
     * The partner password is stored encrypted; decrypt it for API calls only.
     */
    public function getInkassoPassword(): ?string
    {
        $stored = $this->inkasso_settings['password'] ?? null;

        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Appended counterpart of the hidden `inkasso_settings` column, so pages
     * that serialise the whole gym still see the safe settings.
     */
    public function getInkassoAttribute(): array
    {
        return $this->getInkassoSettingsForDisplay();
    }

    /**
     * Settings safe to expose to the frontend: the password is reduced to a
     * boolean flag so the secret never leaves the server.
     */
    public function getInkassoSettingsForDisplay(): array
    {
        $settings = $this->inkasso_settings;
        $settings['has_password'] = ! empty($settings['password']);
        unset($settings['password']);

        return $settings;
    }

    /**
     * The configured fee for a dunning level, falling back to the defaults.
     */
    public function getDunningFee(int $level): float
    {
        foreach ($this->inkasso_settings['levels'] ?? [] as $config) {
            if ((int) ($config['level'] ?? 0) === $level) {
                return (float) ($config['fee'] ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * Days the member is given to pay after a notice of this level was sent.
     *
     * Settings saved before this option existed carry no value, so the default
     * period applies until the gym configures one.
     */
    public function getDunningPaymentPeriodDays(int $level): int
    {
        foreach ($this->inkasso_settings['levels'] ?? [] as $config) {
            if ((int) ($config['level'] ?? 0) === $level) {
                $days = $config['payment_period_days'] ?? null;

                return $days === null ? self::DEFAULT_PAYMENT_PERIOD_DAYS : (int) $days;
            }
        }

        return self::DEFAULT_PAYMENT_PERIOD_DAYS;
    }
}
