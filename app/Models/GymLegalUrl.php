<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GymLegalUrl extends Model
{
    use HasFactory;

    const TYPE_TERMS_AND_CONDITIONS = 'terms_and_conditions';
    const TYPE_CANCELLATION_POLICY = 'cancellation_policy';
    const TYPE_PRIVACY_POLICY = 'privacy_policy';
    const TYPE_TERMS_OF_USE = 'terms_of_use';
    const TYPE_PRICING = 'pricing';
    const TYPE_CONTRACT_CONCLUSION = 'contract_conclusion';

    protected $fillable = [
        'gym_id',
        'type',
        'url',
    ];

    /**
     * Alle verfügbaren Typen mit deutschen Labels
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_TERMS_AND_CONDITIONS => 'Allgemeine Geschäftsbedingungen',
            self::TYPE_CANCELLATION_POLICY => 'Widerrufsbelehrung',
            self::TYPE_PRIVACY_POLICY => 'Datenschutzerklärung',
            self::TYPE_TERMS_OF_USE => 'Nutzungsbedingungen',
            self::TYPE_PRICING => 'Tarife',
            self::TYPE_CONTRACT_CONCLUSION => 'Vertragsabschluss',
        ];
    }

    /**
     * Label für einen Typ zurückgeben
     */
    public static function getTypeLabel(string $type): string
    {
        return self::getTypes()[$type] ?? $type;
    }

    /**
     * Beziehung zum Gym
     */
    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    /**
     * Deutsches Label für diesen Eintrag
     */
    public function getLabelAttribute(): string
    {
        return self::getTypeLabel($this->type);
    }
}
