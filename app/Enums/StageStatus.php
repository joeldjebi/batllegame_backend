<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * Lifecycle of a stage. Online: Pending -> Submissions -> Voting -> Closed.
 * On-site: Pending -> Voting (matches opened live) -> Closed.
 */
enum StageStatus: string implements HasBadge
{
    use EnumHelpers;

    case Pending = 'en_attente';
    case Submissions = 'soumissions';
    case Voting = 'vote';
    case Closed = 'cloture';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'À venir',
            self::Submissions => 'Soumissions ouvertes',
            self::Voting => 'Vote en cours',
            self::Closed => 'Terminée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Submissions => 'blue',
            self::Voting => 'fuchsia',
            self::Closed => 'green',
        };
    }
}
