<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * Lifecycle of a competition.
 */
enum CompetitionStatus: string implements HasBadge
{
    use EnumHelpers;

    case Draft = 'brouillon';
    case Registration = 'inscriptions';
    case InProgress = 'en_cours';
    case Finished = 'terminee';
    case Cancelled = 'annulee';

    /**
     * Allowed lifecycle transitions.
     *
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Draft => [self::Registration, self::Cancelled],
            self::Registration => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Finished, self::Cancelled],
            self::Finished, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->nextStatuses(), true);
    }

    /**
     * Visible to the public (mobile app).
     */
    public function isPublic(): bool
    {
        return $this !== self::Draft;
    }

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

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Registration => 'blue',
            self::InProgress => 'violet',
            self::Finished => 'green',
            self::Cancelled => 'red',
        };
    }
}
