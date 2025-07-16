<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_schedule_id',
        'member_id',
        'status',
        'cancelled_at',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
    ];

    public function courseSchedule()
    {
        return $this->belongsTo(CourseSchedule::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function getStatusTextAttribute()
    {
        return [
            'booked' => 'Gebucht',
            'attended' => 'Teilgenommen',
            'no_show' => 'Nicht erschienen',
            'cancelled' => 'Storniert',
        ][$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute()
    {
        return [
            'booked' => 'blue',
            'attended' => 'green',
            'no_show' => 'red',
            'cancelled' => 'gray',
        ][$this->status] ?? 'gray';
    }

    public function getIsBookedAttribute()
    {
        return $this->status === 'booked';
    }

    public function getIsAttendedAttribute()
    {
        return $this->status === 'attended';
    }

    public function getIsNoShowAttribute()
    {
        return $this->status === 'no_show';
    }

    public function getIsCancelledAttribute()
    {
        return $this->status === 'cancelled';
    }

    public function scopeBooked($query)
    {
        return $query->where('status', 'booked');
    }

    public function scopeAttended($query)
    {
        return $query->where('status', 'attended');
    }

    public function scopeNoShow($query)
    {
        return $query->where('status', 'no_show');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
