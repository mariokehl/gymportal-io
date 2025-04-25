<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gym extends Model
{
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
    ];

    protected $casts = [
        'subscription_ends_at' => 'datetime',
    ];

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
}
