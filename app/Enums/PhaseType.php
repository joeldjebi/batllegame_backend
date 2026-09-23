<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Format of a competition phase.
 */
enum PhaseType: string
{
    use EnumHelpers;

    case Groups = 'poules';
    case SingleElimination = 'elimination';
    case DoubleElimination = 'double_elimination';

    public function label(): string
    {
        return match ($this) {
            self::Groups => 'Poules',
            self::SingleElimination => 'Élimination simple',
            self::DoubleElimination => 'Double élimination',
        };
    }
}
