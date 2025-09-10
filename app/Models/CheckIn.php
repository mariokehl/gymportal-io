<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'gym_id',
        'check_in_time',
        'check_out_time',
        'check_in_method',
        'checked_in_by',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
    ];

    protected $appends = ['check_in_method_text'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function getDurationAttribute()
    {
        if (!$this->check_out_time) {
            return null;
        }

        return $this->check_in_time->diffInMinutes($this->check_out_time);
    }

    public function getDurationFormattedAttribute()
    {
        if (!$this->duration) {
            return 'Noch anwesend';
        }

        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        return "{$hours}h {$minutes}min";
    }

    public function getCheckInMethodTextAttribute()
    {
        return [
            'qr_code' => 'QR-Code',
            'nfc_card' => 'NFC-Tag',
            'manual' => 'Manuell',
        ][$this->check_in_method] ?? $this->check_in_method;
    }

    public function scopeToday($query)
    {
        return $query->whereDate('check_in_time', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('check_in_time', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('check_in_time', [now()->startOfMonth(), now()->endOfMonth()]);
    }
}
