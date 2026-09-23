<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Ordered tie-break criteria of a phase; each one is applied where it makes sense (match or group standings).
 */
enum TieBreaker: string
{
    use EnumHelpers;

    case JuryScore = 'jury';
    case PublicScore = 'public';
    case HeadToHead = 'confrontation_directe';
    case ScoreDiff = 'difference_score';
    case Seed = 'seed';

    public function label(): string
    {
        return match ($this) {
            self::JuryScore => 'Score du jury',
            self::PublicScore => 'Score du public',
            self::HeadToHead => 'Confrontation directe',
            self::ScoreDiff => 'Différence de score',
            self::Seed => 'Tête de série',
        };
    }
}
