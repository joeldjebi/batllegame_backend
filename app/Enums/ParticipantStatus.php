<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * State of a participant within a competition.
 */
enum ParticipantStatus: string implements HasBadge
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

    public function tone(): string
    {
        return match ($this) {
            self::Registered => 'amber',
            self::Validated => 'green',
            self::Eliminated => 'gray',
            self::Withdrawn => 'gray',
            self::Disqualified => 'red',
        };
    }
}
