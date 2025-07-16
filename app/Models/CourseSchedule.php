<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'instructor_id',
        'date',
        'start_time',
        'end_time',
        'room',
        'is_cancelled',
        'cancellation_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'is_cancelled' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function bookings()
    {
        return $this->hasMany(CourseBooking::class);
    }

    public function getStartDateTimeAttribute()
    {
        return $this->date->setTimeFromTimeString($this->start_time);
    }

    public function getEndDateTimeAttribute()
    {
        return $this->date->copy()->setTimeFromTimeString($this->end_time);
    }

    public function getIsFullAttribute()
    {
        return $this->bookings()->where('status', 'booked')->count() >= $this->course->capacity;
    }

    public function getBookingsCountAttribute()
    {
        return $this->bookings()->where('status', 'booked')->count();
    }

    public function getRemainingSpacesAttribute()
    {
        return max(0, $this->course->capacity - $this->bookings_count);
    }

    public function getIsPastAttribute()
    {
        return $this->end_date_time->isPast();
    }

    public function getIsCurrentAttribute()
    {
        return $this->start_date_time->isPast() && $this->end_date_time->isFuture();
    }

    public function getIsUpcomingAttribute()
    {
        return $this->start_date_time->isFuture();
    }

    public function scopeFuture($query)
    {
        return $query->where('date', '>', now()->format('Y-m-d'))
                     ->orWhere(function ($query) {
                         $query->where('date', '=', now()->format('Y-m-d'))
                               ->where('start_time', '>', now()->format('H:i:s'));
                     });
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_cancelled', false);
    }

    public function scopeCancelled($query)
    {
        return $query->where('is_cancelled', true);
    }
}
