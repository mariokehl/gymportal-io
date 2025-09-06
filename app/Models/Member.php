<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasFactory, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'gym_id',
        'user_id',
        'member_number',
        'salutation',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'address',
        'address_addition',
        'voucher_code',
        'fitness_goals',
        'city',
        'postal_code',
        'country',
        'status',
        'profile_photo_path',
        'joined_date',
        'notes',
        'emergency_contact_name',
        'emergency_contact_phone',
        'registration_source',
        'widget_data',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'joined_date' => 'date',
        'widget_data' => 'array',
    ];

    protected $appends = ['initials', 'full_name', 'status_text', 'status_color'];

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function activeMembership()
    {
        return $this->memberships()->where('status', 'active')->first();
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function defaultPaymentMethod(): HasOne
    {
        return $this->hasOne(PaymentMethod::class)->where('is_default', true);
    }

    public function activePaymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class)->where('status', 'active');
    }

    /**
     * Alle Zahlungen des Mitglieds über alle Mitgliedschaften
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Payment::class,
            Membership::class,
            'member_id',      // Foreign key auf memberships table
            'membership_id',  // Foreign key auf payments table
            'id',            // Local key auf members table
            'id'             // Local key auf memberships table
        )->orderBy('payments.created_at', 'desc');
    }

    /**
     * Direkte Zahlungen des Mitglieds (falls es auch direkte Zahlungen gibt)
     */
    public function directPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'member_id')->orderBy('created_at', 'desc');
    }

    /**
     * Alle Zahlungen des Mitglieds (sowohl über Mitgliedschaften als auch direkte)
     */
    public function allPayments()
    {
        // Kombiniert Zahlungen über Mitgliedschaften und direkte Zahlungen
        $membershipPayments = $this->payments()->get();
        $directPayments = $this->directPayments()->get();

        return $membershipPayments->merge($directPayments)->sortByDesc('created_at');
    }

    public function checkIns()
    {
        return $this->hasMany(CheckIn::class);
    }

    public function courseBookings()
    {
        return $this->hasMany(CourseBooking::class);
    }

    public function widgetRegistrations()
    {
        return $this->hasMany(WidgetRegistration::class);
    }

    public function notificationRecipients()
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    // SEPA-spezifische Relationships
    public function sepaPaymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class)->sepa();
    }

    public function activeSepaPaymentMethod(): HasOne
    {
        return $this->hasOne(PaymentMethod::class)
                    ->sepa()
                    ->where('sepa_mandate_status', 'active');
    }

    public function pendingSepaPaymentMethod(): HasOne
    {
        return $this->hasOne(PaymentMethod::class)
                    ->sepa()
                    ->where('sepa_mandate_status', 'pending');
    }

    // SEPA-spezifische Methoden
    public function hasActiveSepaMandate(): bool
    {
        return $this->activeSepaPaymentMethod !== null;
    }

    public function requiresSepaMandate(): bool
    {
        // Prüfen ob aktuelle Mitgliedschaft SEPA-Lastschrift verwendet
        $activeMembership = $this->activeMembership();
        if (!$activeMembership) {
            return false;
        }

        return $activeMembership->payment_method === 'sepa_direct_debit';
    }

    public function createSepaPaymentMethod(bool $acknowledgedOnline = false): PaymentMethod
    {
        return PaymentMethod::createSepaPaymentMethod($this, $acknowledgedOnline);
    }

    public function getSepaMandateStatusAttribute(): ?string
    {
        $sepaPayment = $this->sepaPaymentMethods()->latest()->first();
        return $sepaPayment ? $sepaPayment->sepa_mandate_status_text : null;
    }

    public function hasPendingSepaMandate(): bool
    {
        return $this->sepaPaymentMethods()
                    ->where('sepa_mandate_status', 'pending')
                    ->exists();
    }

    // Status-Management Methoden
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function activateMember(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        return $this->update(['status' => 'active']);
    }

    public function setPending(?string $reason = null): bool
    {
        $this->update([
            'status' => 'pending',
            'widget_data' => array_merge($this->widget_data ?? [], [
                'pending_reason' => $reason,
                'pending_since' => now()->toISOString(),
            ])
        ]);

        return true;
    }

    // Erweiterte Status-Attribute
    public function getStatusTextAttribute()
    {
        return [
            'active' => 'Aktiv',
            'inactive' => 'Inaktiv',
            'paused' => 'Pausiert',
            'overdue' => 'Überfällig',
            'pending' => 'Ausstehend', // Neu hinzugefügt
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'active' => 'green',
            'inactive' => 'gray',
            'paused' => 'yellow',
            'overdue' => 'red',
            'pending' => 'orange', // Neu hinzugefügt
        ][$this->status] ?? 'gray';
    }

    public function getStatusDescriptionAttribute(): string
    {
        return match($this->status) {
            'active' => 'Mitgliedschaft ist aktiv und alle Dienste verfügbar',
            'inactive' => 'Mitgliedschaft ist inaktiv',
            'paused' => 'Mitgliedschaft ist temporär pausiert',
            'overdue' => 'Zahlung ist überfällig',
            'pending' => 'Mitgliedschaft wartet auf Aktivierung (z.B. Zahlungsbestätigung oder SEPA-Mandat)',
            default => 'Unbekannter Status'
        };
    }

    // Bestehende Methoden bleiben unverändert
    public function getInitialsAttribute(): string
    {
        return substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1);
    }

    public function fullName()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getFullAddressAttribute()
    {
        $address = $this->address;
        if ($this->address_addition) {
            $address .= ' ' . $this->address_addition;
        }
        if ($this->postal_code && $this->city) {
            $address .= ', ' . $this->postal_code . ' ' . $this->city;
        }
        return $address;
    }

    public function getAgeAttribute()
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    public function getLastCheckInAttribute()
    {
        return $this->checkIns()->latest('check_in_time')->first();
    }

    /**
     * Get the status history for the member
     */
    public function statusHistory()
    {
        return $this->hasMany(MemberStatusHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest status change
     */
    public function getLatestStatusChangeAttribute()
    {
        return $this->statusHistory()->first();
    }

    /**
     * Get recent status history (last 30 days)
     */
    public function recentStatusHistory()
    {
        return $this->statusHistory()->where('created_at', '>=', now()->subDays(30));
    }

    /**
     * Check if member was recently activated
     */
    public function wasRecentlyActivated(): bool
    {
        return $this->statusHistory()
            ->where('new_status', 'active')
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();
    }

    /**
     * Get the reason for current status
     */
    public function getCurrentStatusReasonAttribute(): ?string
    {
        $lastChange = $this->statusHistory()
            ->where('new_status', $this->status)
            ->first();

        return $lastChange ? $lastChange->reason : null;
    }

    /**
     * Get pause information from status history
     */
    public function getPauseInfoAttribute(): ?array
    {
        if ($this->status !== 'paused') {
            return null;
        }

        $pauseRecord = $this->statusHistory()
            ->where('new_status', 'paused')
            ->latest()
            ->first();

        if (!$pauseRecord) {
            return null;
        }

        return [
            'paused_at' => $pauseRecord->created_at,
            'reason' => $pauseRecord->reason,
            'paused_by' => $pauseRecord->changedBy ? $pauseRecord->changedBy->name : 'System',
            'triggered_by' => $pauseRecord->metadata['triggered_by'] ?? null
        ];
    }

    /**
     * Check if member was paused due to overdue payment
     */
    public function isPausedDueToOverdue(): bool
    {
        if ($this->status !== 'paused') {
            return false;
        }

        $lastPause = $this->statusHistory()
            ->where('new_status', 'paused')
            ->latest()
            ->first();

        return $lastPause && ($lastPause->metadata['triggered_by'] ?? null) === 'payment_overdue';
    }

    /**
     * Get status transition count
     */
    public function getStatusChangeCountAttribute(): int
    {
        return $this->statusHistory()->count();
    }


    /**
     * Get available status transitions based on current state
     */
    public function getAvailableStatusTransitionsAttribute(): array
    {
        $current = $this->status;
        $available = [];

        // Define possible transitions
        $transitions = [
            'active' => ['inactive', 'paused', 'overdue'],
            'inactive' => ['active', 'pending'],
            'paused' => ['active', 'inactive', 'overdue'],
            'overdue' => ['active', 'inactive'],
            'pending' => ['active', 'inactive']
        ];

        return $transitions[$current] ?? [];
    }

    /**
     * Log a status change with context
     */
    public function logStatusChange(
        string $newStatus,
        ?string $reason = null,
        ?array $metadata = null
    ): MemberStatusHistory {
        return MemberStatusHistory::recordChange(
            $this,
            $this->status,
            $newStatus,
            $reason,
            $metadata
        );
    }

    // Erweiterte Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFromWidget($query)
    {
        return $query->where('registration_source', 'widget');
    }

    // Neue kombinierte Scopes
    public function scopeActiveOrPending($query)
    {
        return $query->whereIn('status', ['active', 'pending']);
    }

    public function scopeRequiringAction($query)
    {
        return $query->whereIn('status', ['pending', 'overdue']);
    }

    // SEPA-spezifische Scopes
    public function scopeWithActiveSepaMandate($query)
    {
        return $query->whereHas('activeSepaPaymentMethod');
    }

    public function scopeWithPendingSepaMandate($query)
    {
        return $query->whereHas('sepaPaymentMethods', function($q) {
            $q->where('sepa_mandate_status', 'pending');
        });
    }

    public function scopeRequiringSepaMandate($query)
    {
        return $query->whereHas('memberships', function($q) {
            $q->where('status', 'active')
              ->where('payment_method', 'sepa_direct_debit');
        });
    }

    // Neue Scope: Members die aufgrund SEPA-Mandaten pending sind
    public function scopePendingDueToSepa($query)
    {
        return $query->where('status', 'pending')
                    ->whereHas('sepaPaymentMethods', function($q) {
                        $q->where('sepa_mandate_status', 'pending');
                    });
    }

    /**
     * Prüft ob das Mitglied inaktiviert werden kann
     */
    public function canBeInactivated(): bool
    {
        // Keine aktiven/ausstehenden Mitgliedschaften
        $hasActiveMemberships = $this->memberships()
            ->whereIn('memberships.status', ['active', 'pending'])
            ->exists();

        if ($hasActiveMemberships) {
            return false;
        }

        // Keine ausstehenden Zahlungen
        $hasPendingPayments = $this->payments()
            ->where('payments.status', 'pending')
            ->exists();

        return !$hasPendingPayments;
    }

    /**
     * Prüft ob das Mitglied aktiviert werden kann (von pending)
     */
    public function canBeActivatedFromPending(): bool
    {
        // Prüfe SEPA-Mandate
        $needsActiveSepaMandate = $this->paymentMethods()
            ->where('requires_mandate', true)
            ->where('sepa_mandate_status', '!=', 'active')
            ->exists();

        if ($needsActiveSepaMandate) {
            return false;
        }

        // Prüfe aktive Zahlungsmethode
        return $this->paymentMethods()
            ->where('payment_methods.status', 'active')
            ->exists();
    }

    /**
     * Prüft ob das Mitglied aktiviert werden kann (von overdue)
     */
    public function canBeActivatedFromOverdue(): bool
    {
        // Keine überfälligen Zahlungen
        $hasOverduePayments = $this->payments()
            ->where('payments.status', 'pending')
            ->where('payments.due_date', '<', now())
            ->exists();

        return !$hasOverduePayments;
    }

    /**
     * Prüft ob das Mitglied pausiert werden kann
     */
    public function canBePaused(): bool
    {
        return $this->memberships()
            ->where('memberships.status', 'active')
            ->whereNull('memberships.pause_start_date')
            ->exists();
    }

    /**
     * Prüft ob das Mitglied als überfällig markiert werden kann
     */
    public function canBeMarkedOverdue(): bool
    {
        return in_array($this->status, ['active', 'paused']);
    }

    /**
     * Gibt den Grund zurück, warum ein Status-Wechsel nicht möglich ist
     */
    public function getStatusChangeBlockReason(string $newStatus): ?string
    {
        // Gleicher Status
        if ($this->status === $newStatus) {
            return null; // Wird im Controller geprüft
        }

        switch ($newStatus) {
            case 'inactive':
                if (!$this->canBeInactivated()) {
                    // Detaillierte Fehleranalyse
                    $hasActiveMemberships = $this->memberships()
                        ->whereIn('memberships.status', ['active', 'pending'])
                        ->exists();

                    if ($hasActiveMemberships) {
                        $activeCount = $this->memberships()
                            ->where('memberships.status', 'active')
                            ->count();
                        $pendingCount = $this->memberships()
                            ->where('memberships.status', 'pending')
                            ->count();

                        if ($activeCount > 0 && $pendingCount > 0) {
                            return "Mitglied kann nicht inaktiviert werden - {$activeCount} aktive und {$pendingCount} ausstehende Mitgliedschaft(en) vorhanden.";
                        } elseif ($activeCount > 0) {
                            return "Mitglied kann nicht inaktiviert werden - {$activeCount} aktive Mitgliedschaft(en) vorhanden.";
                        } else {
                            return "Mitglied kann nicht inaktiviert werden - {$pendingCount} ausstehende Mitgliedschaft(en) vorhanden.";
                        }
                    }

                    $pendingPaymentsCount = $this->payments()
                        ->where('payments.status', 'pending')
                        ->count();

                    if ($pendingPaymentsCount > 0) {
                        return "Mitglied kann nicht inaktiviert werden - {$pendingPaymentsCount} ausstehende Zahlung(en) vorhanden.";
                    }

                    return 'Mitglied kann nicht inaktiviert werden.';
                }
                break;

            case 'active':
                if ($this->status === 'pending' && !$this->canBeActivatedFromPending()) {
                    $needsSepa = $this->paymentMethods()
                        ->where('requires_mandate', true)
                        ->where('sepa_mandate_status', '!=', 'active')
                        ->exists();

                    if ($needsSepa) {
                        $pendingMandates = $this->paymentMethods()
                            ->where('requires_mandate', true)
                            ->where('sepa_mandate_status', 'pending')
                            ->count();

                        if ($pendingMandates > 0) {
                            return "Aktivierung nicht möglich - {$pendingMandates} SEPA-Mandat(e) warten auf Unterschrift.";
                        }

                        return 'Aktivierung nicht möglich - SEPA-Mandat nicht aktiv.';
                    }

                    $hasPaymentMethod = $this->paymentMethods()
                        ->where('payment_methods.status', 'active')
                        ->exists();

                    if (!$hasPaymentMethod) {
                        return 'Aktivierung nicht möglich - keine aktive Zahlungsmethode vorhanden.';
                    }
                }

                if ($this->status === 'overdue' && !$this->canBeActivatedFromOverdue()) {
                    $overdueCount = $this->payments()
                        ->where('payments.status', 'pending')
                        ->where('payments.due_date', '<', now())
                        ->count();

                    $overdueAmount = $this->payments()
                        ->where('payments.status', 'pending')
                        ->where('payments.due_date', '<', now())
                        ->sum('amount');

                    if ($overdueCount > 0) {
                        return sprintf(
                            'Aktivierung nicht möglich - %d überfällige Zahlung(en) im Gesamtwert von %.2f € nicht beglichen.',
                            $overdueCount,
                            $overdueAmount
                        );
                    }

                    return 'Aktivierung nicht möglich - überfällige Zahlungen nicht beglichen.';
                }
                break;

            case 'paused':
                if (!$this->canBePaused()) {
                    $activeMemberships = $this->memberships()
                        ->where('memberships.status', 'active')
                        ->count();

                    if ($activeMemberships === 0) {
                        return 'Pausierung nicht möglich - keine aktive Mitgliedschaft vorhanden.';
                    }

                    $alreadyPaused = $this->memberships()
                        ->where('memberships.status', 'active')
                        ->whereNotNull('memberships.pause_start_date')
                        ->exists();

                    if ($alreadyPaused) {
                        return 'Pausierung nicht möglich - Mitgliedschaft ist bereits pausiert.';
                    }

                    return 'Pausierung nicht möglich.';
                }
                break;

            case 'overdue':
                if (!$this->canBeMarkedOverdue()) {
                    return 'Überfällig-Status kann nur von aktiven oder pausierten Mitgliedern gesetzt werden.';
                }
                break;
        }

        return null;
    }

    /**
     * Validiert ob ein Status-Wechsel erlaubt ist
     */
    public function validateStatusChange(string $newStatus): array
    {
        $blockReason = $this->getStatusChangeBlockReason($newStatus);

        return [
            'allowed' => $blockReason === null,
            'reason' => $blockReason
        ];
    }

    /**
     * Führt einen Status-Wechsel durch (wenn erlaubt)
     */
    public function changeStatusTo(string $newStatus, ?string $reason = null): bool
    {
        $validation = $this->validateStatusChange($newStatus);

        if (!$validation['allowed']) {
            throw new \Exception($validation['reason']);
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;
        $result = $this->save();

        if ($result) {
            // Log die Änderung
            MemberStatusHistory::recordChange(
                $this,
                $oldStatus,
                $newStatus,
                $reason
            );
        }

        return $result;
    }

    /**
     * Prüft ob das Mitglied gelöscht werden kann
     */
    public function canBeDeleted(): bool
    {
        // Nur inaktive Mitglieder können gelöscht werden
        if ($this->status !== 'inactive') {
            return false;
        }

        // Keine aktiven oder ausstehenden Mitgliedschaften
        $hasActiveMemberships = $this->memberships()
            ->whereIn('memberships.status', ['active', 'pending'])
            ->exists();

        if ($hasActiveMemberships) {
            return false;
        }

        // Keine ausstehenden Zahlungen
        $hasPendingPayments = $this->payments()
            ->where('payments.status', 'pending')
            ->exists();

        if ($hasPendingPayments) {
            return false;
        }

        // Keine offenen SEPA-Mandate
        $hasOpenSepaMandates = $this->sepaPaymentMethods()
            ->whereIn('sepa_mandate_status', ['pending', 'active'])
            ->exists();

        if ($hasOpenSepaMandates) {
            return false;
        }

        return true;
    }

    /**
     * Gibt detaillierte Informationen zurück, warum nicht gelöscht werden kann
     */
    public function getDeleteBlockReason(): ?array
    {
        if ($this->status !== 'inactive') {
            return [
                'reason' => 'Status muss "Inaktiv" sein',
                'current_status' => $this->status_text,
                'type' => 'status'
            ];
        }

        $activeMemberships = $this->memberships()
            ->whereIn('status', ['active', 'pending'])
            ->count();

        if ($activeMemberships > 0) {
            return [
                'reason' => "Es existieren noch {$activeMemberships} aktive/ausstehende Mitgliedschaft(en)",
                'count' => $activeMemberships,
                'type' => 'memberships'
            ];
        }

        $pendingPayments = $this->payments()
            ->where('status', 'pending')
            ->count();

        if ($pendingPayments > 0) {
            $totalAmount = $this->payments()
                ->where('status', 'pending')
                ->sum('amount');

            return [
                'reason' => sprintf('%d ausstehende Zahlung(en) im Wert von %.2f €',
                    $pendingPayments, $totalAmount),
                'count' => $pendingPayments,
                'amount' => $totalAmount,
                'type' => 'payments'
            ];
        }

        $openMandates = $this->sepaPaymentMethods()
            ->whereIn('sepa_mandate_status', ['pending', 'active'])
            ->count();

        if ($openMandates > 0) {
            return [
                'reason' => "Es existieren noch {$openMandates} offene SEPA-Mandate",
                'count' => $openMandates,
                'type' => 'sepa'
            ];
        }

        return null;
    }

    /**
     * Get the access configuration for the member
     */
    public function accessConfig()
    {
        return $this->hasOne(MemberAccessConfig::class);
    }

    /**
     * Get access logs for the member
     */
    public function accessLogs()
    {
        return $this->hasMany(MemberAccessLog::class);
    }

    /**
     * Get or create access configuration
     */
    public function getOrCreateAccessConfig(): MemberAccessConfig
    {
        return $this->accessConfig()->firstOrCreate([
            'member_id' => $this->id
        ], [
            'qr_code_enabled' => true,
            'nfc_enabled' => false,
        ]);
    }

    /**
     * Check if member has any active access method
     */
    public function hasActiveAccessMethod(): bool
    {
        $config = $this->accessConfig;

        if (!$config) {
            return true; // QR is enabled by default
        }

        return $config->qr_code_enabled || $config->nfc_enabled;
    }

    /**
     * Check if member can access a specific service
     */
    public function canAccessService(string $service): bool
    {
        // Check member status first
        if ($this->status !== 'active') {
            return false;
        }

        // Check for active membership
        if (!$this->activeMembership()) {
            return false;
        }

        $config = $this->accessConfig;

        // Gym access is allowed by default if member is active
        if ($service === 'gym') {
            return true;
        }

        // Other services require configuration
        if (!$config) {
            return false;
        }

        return $config->canAccessService($service);
    }

    /**
     * Generate QR code data for member
     */
    public function generateQrCodeData(): string
    {
        $timestamp = time();
        $gym = $this->gym;

        if (!$gym->scanner_secret_key) {
            $gym->generateScannerSecretKey();
        }

        $message = "{$this->member_number}:{$timestamp}";
        $hash = hash_hmac('sha256', $message, $gym->scanner_secret_key);

        return "{$this->member_number}:{$timestamp}:{$hash}";
    }

    /**
     * Get recent access attempts
     */
    public function getRecentAccessAttempts(int $limit = 10)
    {
        return $this->accessLogs()
            ->where('action', MemberAccessLog::ACTION_ACCESS_ATTEMPT)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get access statistics
     */
    public function getAccessStatistics(?string $period = '30d'): array
    {
        return MemberAccessLog::getMemberStatistics($this->id, $period);
    }

    /**
     * Log successful access
     */
    public function logSuccessfulAccess(string $service, string $method, ?string $deviceId = null): void
    {
        MemberAccessLog::create([
            'member_id' => $this->id,
            'action' => MemberAccessLog::ACTION_ACCESS_ATTEMPT,
            'service' => $service,
            'method' => $method,
            'success' => true,
            'device_id' => $deviceId,
            'accessed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Update last check-in if it's gym access
        if ($service === 'gym') {
            CheckIn::create([
                'member_id' => $this->id,
                'gym_id' => $this->gym_id,
                'check_in_time' => now(),
                'check_in_method' => $method,
            ]);
        }
    }

    /**
     * Log failed access attempt
     */
    public function logFailedAccess(string $service, string $method, string $reason, ?string $deviceId = null): void
    {
        MemberAccessLog::create([
            'member_id' => $this->id,
            'action' => MemberAccessLog::ACTION_ACCESS_ATTEMPT,
            'service' => $service,
            'method' => $method,
            'success' => false,
            'device_id' => $deviceId,
            'accessed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Check if member has NFC configured
     */
    public function hasNfcConfigured(): bool
    {
        return $this->accessConfig &&
               $this->accessConfig->nfc_enabled &&
               $this->accessConfig->nfc_uid;
    }

    /**
     * Check if member has valid QR code
     */
    public function hasValidQrCode(): bool
    {
        if (!$this->accessConfig) {
            return true; // QR is enabled by default
        }

        return $this->accessConfig->hasValidQrCode();
    }

    /**
     * Scope: Members with access configuration
     */
    public function scopeWithAccessConfig($query)
    {
        return $query->with('accessConfig');
    }

    /**
     * Scope: Members with specific access method
     */
    public function scopeWithAccessMethod($query, string $method)
    {
        return $query->whereHas('accessConfig', function ($q) use ($method) {
            if ($method === 'qr') {
                $q->where('qr_code_enabled', true);
            } elseif ($method === 'nfc') {
                $q->where('nfc_enabled', true)->whereNotNull('nfc_uid');
            }
        });
    }

    /**
     * Scope: Members with service access
     */
    public function scopeWithServiceAccess($query, string $service)
    {
        return $query->whereHas('accessConfig', function ($q) use ($service) {
            $q->withServiceEnabled($service);
        });
    }

    /**
     * Scope für Mitglieder die inaktiviert werden können
     */
    public function scopeCanBeInactivated($query)
    {
        return $query->whereDoesntHave('memberships', function($q) {
            $q->whereIn('memberships.status', ['active', 'pending']);
        })->whereDoesntHave('payments', function($q) {
            $q->where('payments.status', 'pending');
        });
    }

    /**
     * Scope für löschbare Mitglieder
     */
    public function scopeDeletable($query)
    {
        return $query->where('status', 'inactive')
            ->whereDoesntHave('memberships', function($q) {
                $q->whereIn('status', ['active', 'pending']);
            })
            ->whereDoesntHave('payments', function($q) {
                $q->where('status', 'pending');
            })
            ->whereDoesntHave('sepaPaymentMethods', function($q) {
                $q->whereIn('sepa_mandate_status', ['pending', 'active']);
            });
    }

    /**
     * Scope für Mitglieder mit aktiven oder ausstehenden Mitgliedschaften
     */
    public function scopeWithActiveMemberships($query)
    {
        return $query->whereHas('memberships', function($q) {
            $q->whereIn('memberships.status', ['active', 'pending']);
        });
    }

    /**
     * Scope für Mitglieder mit ausstehenden Zahlungen
     */
    public function scopeWithPendingPayments($query)
    {
        return $query->whereHas('payments', function($q) {
            $q->where('payments.status', 'pending');
        });
    }

    /**
     * Scope für Mitglieder mit überfälligen Zahlungen
     */
    public function scopeWithOverduePayments($query)
    {
        return $query->whereHas('payments', function($q) {
            $q->where('payments.status', 'pending')
            ->where('payments.due_date', '<', now());
        });
    }

    /**
     * Gibt Status-Statistiken zurück
     */
    public function getStatusStatistics(): array
    {
        return [
            'can_be_inactivated' => $this->canBeInactivated(),
            'can_be_activated' => $this->status === 'pending' ? $this->canBeActivatedFromPending() :
                                ($this->status === 'overdue' ? $this->canBeActivatedFromOverdue() : false),
            'can_be_paused' => $this->canBePaused(),
            'can_be_marked_overdue' => $this->canBeMarkedOverdue(),
            'active_memberships' => $this->memberships()->where('memberships.status', 'active')->count(),
            'pending_memberships' => $this->memberships()->where('memberships.status', 'pending')->count(),
            'pending_payments' => $this->payments()->where('payments.status', 'pending')->count(),
            'overdue_payments' => $this->payments()
                ->where('payments.status', 'pending')
                ->where('payments.due_date', '<', now())
                ->count()
        ];
    }
}
