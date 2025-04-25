<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gym_id',
        'title',
        'content',
        'type',
        'send_at',
    ];

    protected $casts = [
        'send_at' => 'datetime',
    ];

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function recipients()
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function getTypeTextAttribute()
    {
        return [
            'announcement' => 'Ankündigung',
            'promotion' => 'Aktion',
            'system' => 'System',
            'reminder' => 'Erinnerung',
        ][$this->type] ?? $this->type;
    }

    public function getTypeColorAttribute()
    {
        return [
            'announcement' => 'blue',
            'promotion' => 'green',
            'system' => 'gray',
            'reminder' => 'yellow',
        ][$this->type] ?? 'gray';
    }

    public function scopeAnnouncement($query)
    {
        return $query->where('type', 'announcement');
    }

    public function scopePromotion($query)
    {
        return $query->where('type', 'promotion');
    }

    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    public function scopeReminder($query)
    {
        return $query->where('type', 'reminder');
    }

    public function scopePending($query)
    {
        return $query->where('send_at', '>', now());
    }

    public function scopeSent($query)
    {
        return $query->where('send_at', '<=', now());
    }
}
