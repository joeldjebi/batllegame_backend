<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * Derived state of a pre-selection (from its dates and publication).
 */
enum PreselectionState: string implements HasBadge
{
    use EnumHelpers;

    case Scheduled = 'programmee';
    case Open = 'ouverte';
    case Closed = 'cloturee';
    case Published = 'publiee';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programmée',
            self::Open => 'En cours',
            self::Closed => 'À publier',
            self::Published => 'Sélection publiée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Open => 'fuchsia',
            self::Closed => 'amber',
            self::Published => 'green',
        };
    }
}
