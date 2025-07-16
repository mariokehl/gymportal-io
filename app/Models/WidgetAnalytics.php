<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WidgetAnalytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'event_type',
        'step',
        'data',
        'session_id',
        'ip_address',
        'user_agent',
        'referrer',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    /**
     * Beziehung zum Fitnessstudio
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Scopes
     */
    public function scopeEventType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeStep($query, $step)
    {
        return $query->where('step', $step);
    }

    public function scopeSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Analytics-Event erstellen
     */
    public static function track($gymId, $eventType, $step = null, $data = [])
    {
        return self::create([
            'gym_id' => $gymId,
            'event_type' => $eventType,
            'step' => $step,
            'data' => $data,
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referrer' => request()->header('referer'),
            'created_at' => now(),
        ]);
    }

    /**
     * Event-Typen definieren
     */
    public static function getEventTypes()
    {
        return [
            'view' => 'Widget angezeigt',
            'plan_selected' => 'Plan ausgewählt',
            'form_started' => 'Formular gestartet',
            'form_completed' => 'Formular abgeschlossen',
            'registration_started' => 'Registrierung gestartet',
            'registration_completed' => 'Registrierung abgeschlossen',
            'registration_failed' => 'Registrierung fehlgeschlagen',
            'payment_started' => 'Zahlung gestartet',
            'payment_completed' => 'Zahlung abgeschlossen',
            'payment_failed' => 'Zahlung fehlgeschlagen',
        ];
    }

    /**
     * Schritte definieren
     */
    public static function getSteps()
    {
        return [
            'plans' => 'Tarifauswahl',
            'form' => 'Formular',
            'checkout' => 'Checkout',
            'payment' => 'Zahlung',
            'confirmation' => 'Bestätigung',
        ];
    }
}
