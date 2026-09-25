<?php

namespace App\Enums;

/**
 * Corporate fitness aggregators a member can check in through.
 *
 * The list is static for now and available to every gym. The frontend keeps
 * the matching badge styles in resources/js/utils/aggregators.js.
 */
enum Aggregator: string
{
    case Wellpass = 'wellpass';
    case Hansefit = 'hansefit';
    case UrbanSportsClub = 'usc';
    case Wellhub = 'wellhub';

    public function label(): string
    {
        return match ($this) {
            self::Wellpass => 'EGYM Wellpass',
            self::Hansefit => 'Hansefit',
            self::UrbanSportsClub => 'Urban Sports Club',
            self::Wellhub => 'Wellhub (Gympass)',
        };
    }
}
