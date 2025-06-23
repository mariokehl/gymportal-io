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
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gym) {
            $gym->generateSlug();
        });

        static::updating(function ($gym) {
            if ($gym->isDirty('name')) {
                $gym->generateSlug();
            }
        });
    }

    protected $casts = [
        'subscription_ends_at' => 'datetime'
    ];

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
        return $this->subscription_status === 'active';
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
}
