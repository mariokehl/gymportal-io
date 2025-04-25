<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gym_id',
        'name',
        'description',
        'capacity',
        'duration_minutes',
        'requires_booking',
        'instructor_id',
        'color',
    ];

    protected $casts = [
        'requires_booking' => 'boolean',
    ];

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function schedules()
    {
        return $this->hasMany(CourseSchedule::class);
    }

    public function upcomingSchedules()
    {
        return $this->schedules()
                    ->where('date', '>=', now()->format('Y-m-d'))
                    ->orderBy('date')
                    ->orderBy('start_time');
    }

    public function futureSchedules()
    {
        return $this->schedules()
                    ->where('date', '>', now()->format('Y-m-d'))
                    ->orWhere(function ($query) {
                        $query->where('date', '=', now()->format('Y-m-d'))
                              ->where('start_time', '>', now()->format('H:i:s'));
                    })
                    ->orderBy('date')
                    ->orderBy('start_time');
    }

    public function getDurationFormattedAttribute()
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return "{$hours}h" . ($minutes > 0 ? " {$minutes}min" : "");
        }

        return "{$minutes}min";
    }
}
