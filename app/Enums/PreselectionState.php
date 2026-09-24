<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * Derived state of a pre-selection, from the organizer's timeline:
 * (open as soon as it exists) → ends_at (submissions + likes + jury) → vote_ends_at (likes + jury)
 * → + deliberation_hours (jury only) → to publish.
 */
enum PreselectionState: string implements HasBadge
{
    use EnumHelpers;

    case Scheduled = 'programmee';
    case Open = 'ouverte';
    case Voting = 'vote';
    case Deliberation = 'deliberation';
    case Closed = 'cloturee';
    case Published = 'publiee';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programmée',
            self::Open => 'En cours',
            self::Voting => 'Vote du public',
            self::Deliberation => 'Délibération du jury',
            self::Closed => 'À publier',
            self::Published => 'Sélection publiée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Open => 'fuchsia',
            self::Voting => 'fuchsia',
            self::Deliberation => 'violet',
            self::Closed => 'amber',
            self::Published => 'green',
        };
    }
}
