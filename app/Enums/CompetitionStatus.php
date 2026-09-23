<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Lifecycle of a competition.
 */
enum CompetitionStatus: string
{
    use EnumHelpers;

    case Draft = 'brouillon';
    case Registration = 'inscriptions';
    case InProgress = 'en_cours';
    case Finished = 'terminee';
    case Cancelled = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Registration => 'Inscriptions',
            self::InProgress => 'En cours',
            self::Finished => 'Terminée',
            self::Cancelled => 'Annulée',
        };
    }
}
