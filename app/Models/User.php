<?php

namespace App\Models;

use App\Notifications\ResetPassword as ResetPasswordNotification;
use App\Notifications\VerifyEmailGerman;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes, CanResetPassword;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role_id',
        'phone',
        'current_gym_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = ['full_name'];

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
     *
     * @return BelongsTo
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

    public function fullName()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
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

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function courseSchedules()
    {
        return $this->hasMany(CourseSchedule::class, 'instructor_id');
    }
}
