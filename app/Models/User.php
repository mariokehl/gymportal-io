<?php

namespace App\Models;

use App\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, CanResetPassword;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role_id',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Overrides the default password reset notification
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
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
        return $this->first_name . ' ' . $this->last_name;
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
