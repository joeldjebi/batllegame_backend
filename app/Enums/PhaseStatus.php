<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * Lifecycle of a phase. Rules are frozen once a phase leaves Pending.
 */
enum PhaseStatus: string implements HasBadge
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

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'violet',
            self::Finished => 'green',
        };
    }
}
