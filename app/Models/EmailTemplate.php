<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'gym_id',
        'name',
        'type',
        'subject',
        'body',
        'is_active',
        'is_default',
        'variables'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'variables' => 'array',
    ];

    const TYPES = [
        'welcome' => 'Willkommen',
        'confirmation' => 'Bestätigung',
        'reminder' => 'Erinnerung',
        'cancellation' => 'Kündigung',
        'invoice' => 'Rechnung',
        'payment_failed' => 'Zahlung fehlgeschlagen',
        'general' => 'Allgemein',
    ];

    /**
     * Get the gym that owns the email template.
     */
    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Scope to filter by gym.
     */
    public function scopeForGym($query, $gymId)
    {
        return $query->where('gym_id', $gymId);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter default templates.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Set as default template for this type and gym.
     * Unset other default templates of the same type.
     */
    public function setAsDefault(): void
    {
        // First, unset all other default templates of this type for this gym
        self::where('gym_id', $this->gym_id)
            ->where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Then set this one as default
        $this->update(['is_default' => true]);
    }

    /**
     * Replace placeholders in content with actual data.
     */
    public function replacePlaceholders(array $data): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($data as $placeholder => $value) {
            $subject = str_replace($placeholder, $value, $subject);
            $body = str_replace($placeholder, $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Get available placeholders based on template type.
     */
    public static function getAvailablePlaceholders(): array
    {
        return [
            'member' => [
                '[Vorname]' => 'Vorname des Mitglieds',
                '[Nachname]' => 'Nachname des Mitglieds',
                '[Anrede]' => 'Anrede (Herr/Frau)',
                '[E-Mail]' => 'E-Mail-Adresse',
                '[Mitgliedsnummer]' => 'Mitgliedsnummer',
            ],
            'gym' => [
                '[Fitnessstudio-Name]' => 'Name des Fitnessstudios',
                '[Adresse]' => 'Adresse des Studios',
                '[Telefon]' => 'Telefonnummer',
                '[Website]' => 'Website-URL',
            ],
            'contract' => [
                '[Vertragslaufzeit]' => 'Laufzeit des Vertrags',
                '[Monatsbeitrag]' => 'Monatlicher Beitrag',
                '[Startdatum]' => 'Vertragsbeginn',
                '[Enddatum]' => 'Vertragsende',
            ],
            'system' => [
                '[QR-Code-Link]' => 'Link zum QR-Code',
                '[Mitgliederbereich-Link]' => 'Link zum Mitgliederbereich',
                '[Datum]' => 'Aktuelles Datum',
                '[Uhrzeit]' => 'Aktuelle Uhrzeit',
            ]
        ];
    }

    /**
     * Get all placeholders as flat array.
     */
    public static function getAllPlaceholders(): array
    {
        $placeholders = [];
        foreach (self::getAvailablePlaceholders() as $category => $items) {
            $placeholders = array_merge($placeholders, $items);
        }
        return $placeholders;
    }

    /**
     * Create a duplicate of this template.
     */
    public function duplicate(?string $newName = null): self
    {
        $newName = $newName ?: ($this->name . ' (Kopie)');

        return self::create([
            'gym_id' => $this->gym_id,
            'name' => $newName,
            'type' => $this->type,
            'subject' => $this->subject,
            'body' => $this->body,
            'is_active' => false, // New duplicates are inactive by default
            'is_default' => false, // New duplicates are never default
            'variables' => $this->variables,
        ]);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When setting is_default to true, unset other defaults
        static::updating(function ($template) {
            if ($template->isDirty('is_default') && $template->is_default) {
                self::where('gym_id', $template->gym_id)
                    ->where('type', $template->type)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
