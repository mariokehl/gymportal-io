<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'member_id',
        'user_id',
        'is_read',
        'read_at',
        'delivery_method',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRecipientNameAttribute()
    {
        return $this->member ? $this->member->fullName() : ($this->user ? $this->user->fullName() : null);
    }

    public function getDeliveryMethodTextAttribute()
    {
        return [
            'app' => 'App',
            'email' => 'E-Mail',
            'sms' => 'SMS',
        ][$this->delivery_method] ?? $this->delivery_method;
    }

    public function getStatusTextAttribute()
    {
        return [
            'pending' => 'Ausstehend',
            'sent' => 'Gesendet',
            'failed' => 'Fehlgeschlagen',
        ][$this->status] ?? $this->status;
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByDeliveryMethod($query, $method)
    {
        return $query->where('delivery_method', $method);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function markAsRead()
    {
        $this->is_read = true;
        $this->read_at = now();
        $this->save();

        return $this;
    }

    public function markAsSent()
    {
        $this->status = 'sent';
        $this->sent_at = now();
        $this->save();

        return $this;
    }

    public function markAsFailed()
    {
        $this->status = 'failed';
        $this->save();

        return $this;
    }
}
