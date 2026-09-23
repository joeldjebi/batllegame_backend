<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Lifecycle of a phase. Rules are frozen once a phase leaves Pending.
 */
enum PhaseStatus: string
{
    use EnumHelpers;

    case Pending = 'en_attente';
    case InProgress = 'en_cours';
    case Finished = 'terminee';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::InProgress => 'En cours',
            self::Finished => 'Terminée',
        };
    }
}
