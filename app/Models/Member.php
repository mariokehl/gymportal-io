<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'iban',
        'account_holder',
        'sepa_mandate_accepted',
        'sepa_mandate_date',
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
        'sepa_mandate_accepted' => 'boolean',
        'sepa_mandate_date' => 'datetime',
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

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
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

    public function getStatusTextAttribute()
    {
        return [
            'active' => 'Aktiv',
            'inactive' => 'Inaktiv',
            'paused' => 'Pausiert',
            'overdue' => 'Überfällig',
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'active' => 'green',
            'inactive' => 'gray',
            'paused' => 'yellow',
            'overdue' => 'red',
        ][$this->status] ?? 'gray';
    }

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

    public function scopeFromWidget($query)
    {
        return $query->where('registration_source', 'widget');
    }

    public function scopeWithSepaMandate($query)
    {
        return $query->where('sepa_mandate_accepted', true);
    }
}
