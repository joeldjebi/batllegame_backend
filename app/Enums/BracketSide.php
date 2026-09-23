<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Which bracket an elimination match belongs to (null for group matches).
 */
enum BracketSide: string
{
    use EnumHelpers;

    case Winners = 'gagnants';
    case Losers = 'perdants';
    case GrandFinal = 'grande_finale';

    public function label(): string
    {
        return match ($this) {
            self::Winners => 'Tableau principal',
            self::Losers => 'Tableau des perdants',
            self::GrandFinal => 'Grande finale',
        };
    }
}
