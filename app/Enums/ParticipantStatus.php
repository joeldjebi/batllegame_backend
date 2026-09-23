<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * State of a participant within a competition.
 */
enum ParticipantStatus: string
{
    use EnumHelpers;

    case Registered = 'inscrit';
    case Validated = 'valide';
    case Eliminated = 'elimine';
    case Withdrawn = 'forfait';
    case Disqualified = 'disqualifie';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Inscrit',
            self::Validated => 'Validé',
            self::Eliminated => 'Éliminé',
            self::Withdrawn => 'Forfait',
            self::Disqualified => 'Disqualifié',
        };
    }
}
