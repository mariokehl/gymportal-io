<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Member extends Model
{
    use HasFactory, SoftDeletes;

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

    public function setPending(string $reason = null): bool
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
}
