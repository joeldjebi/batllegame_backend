<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Who decides the outcome of the matches of a phase.
 */
enum VoteMode: string
{
    use EnumHelpers;

    case Jury = 'jury';
    case Public = 'public';
    case Mixed = 'mixte';

    public function label(): string
    {
        return match ($this) {
            self::Jury => 'Jury uniquement',
            self::Public => 'Public uniquement',
            self::Mixed => 'Jury et public',
        };
    }
}
