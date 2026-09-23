<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * Lifecycle of a match.
 */
enum MatchStatus: string implements HasBadge
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

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Submissions => 'blue',
            self::Voting => 'fuchsia',
            self::Closed => 'green',
            self::Cancelled => 'gray',
        };
    }
}
