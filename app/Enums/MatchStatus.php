<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Lifecycle of a match.
 */
enum MatchStatus: string
{
    use EnumHelpers;

    case Scheduled = 'planifie';
    case Submissions = 'soumissions';
    case Voting = 'vote';
    case Closed = 'cloture';
    case Cancelled = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Planifié',
            self::Submissions => 'Soumissions',
            self::Voting => 'Vote',
            self::Closed => 'Clôturé',
            self::Cancelled => 'Annulé',
        };
    }
}
