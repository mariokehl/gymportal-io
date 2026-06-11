<?php

namespace App\Models;

use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Notifications\VerifyEmailGerman;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements MustVerifyEmail
{
    use CanResetPassword, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role_id',
        'phone',
        'current_gym_id',
        'is_blocked',
        'blocked_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];

    protected $appends = ['full_name', 'name'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($user) {
            // Capitalize first letter of specific fields
            $fieldsToCapitalize = ['first_name', 'last_name'];

            foreach ($fieldsToCapitalize as $field) {
                if ($user->$field) {
                    $user->$field = ucfirst($user->$field);
                }
            }
        });
    }

    // Überschreibe die Standard-Verifizierungs-E-Mail
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailGerman);
    }

    // Overrides the default password reset notification
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the current gym that the user is working with.
     */
    public function currentGym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'current_gym_id');
    }

    public function ownedGyms()
    {
        return $this->hasMany(Gym::class, 'owner_id');
    }

    public function gyms()
    {
        return $this->belongsToMany(Gym::class)->withPivot('role')->withTimestamps();
    }

    /**
     * Gyms the user is a member of via the gym_users pivot (excludes owned gyms
     * unless an explicit pivot row exists).
     */
    public function memberGyms(): BelongsToMany
    {
        return $this->belongsToMany(Gym::class, 'gym_users')->withPivot('role')->withTimestamps();
    }

    /**
     * Every gym the user may access: gyms they own plus gyms they are a member
     * of via gym_users, deduplicated by id and keyed for convenient lookup.
     *
     * @return Collection<int, Gym>
     */
    public function accessibleGyms()
    {
        return $this->ownedGyms
            ->concat($this->memberGyms)
            ->unique('id')
            ->values();
    }

    /**
     * The user's effective role within a gym. Ownership always wins and maps to
     * 'owner'; otherwise the gym_users pivot role applies. Returns null when the
     * user has no relationship to the gym.
     */
    public function roleInGym(Gym $gym): ?string
    {
        if ($this->id === $gym->owner_id) {
            return 'owner';
        }

        $membership = $this->memberGyms->firstWhere('id', $gym->id)
            ?? $this->memberGyms()->whereKey($gym->id)->first();

        return $membership?->pivot->role;
    }

    /**
     * Whether the user may access the gym at all (member or owner). Required to
     * switch into the gym and render its pages.
     */
    public function belongsToGym(Gym $gym): bool
    {
        return $this->roleInGym($gym) !== null;
    }

    /**
     * Whether the user may use management functions (settings, team, scanners …)
     * within the gym. Only owners and admins manage; staff and trainers cannot.
     */
    public function canManageGym(Gym $gym): bool
    {
        return in_array($this->roleInGym($gym), ['owner', 'admin'], true);
    }

    public function fullName()
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    public function isAdmin()
    {
        return $this->role->slug === 'admin';
    }

    public function isOwner()
    {
        return $this->role->slug === 'owner';
    }

    public function isStaff()
    {
        return $this->role->slug === 'staff';
    }

    public function isTrainer()
    {
        return $this->role->slug === 'trainer';
    }

    public function isMember()
    {
        return $this->role->slug === 'member';
    }

    public function isBlocked()
    {
        return $this->is_blocked;
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function courseSchedules()
    {
        return $this->hasMany(CourseSchedule::class, 'instructor_id');
    }
}
