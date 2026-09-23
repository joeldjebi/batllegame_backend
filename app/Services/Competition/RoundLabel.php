<?php

namespace App\Services\Competition;

use App\Enums\BracketSide;

/**
 * Human names of bracket rounds ("Demi-finales", "Finale"...), shared by the
 * bracket view and the stages.
 */
final class RoundLabel
{
    public static function for(?BracketSide $side, int $round, int $lastRound): string
    {
        return match (true) {
            $side === BracketSide::GrandFinal => $round === 1 ? 'Grande finale' : 'Finale « reset »',
            $side === BracketSide::Losers => $round === $lastRound ? 'Finale perdants' : 'Tour perdants '.$round,
            $round === $lastRound => 'Finale',
            $round === $lastRound - 1 => 'Demi-finales',
            $round === $lastRound - 2 => 'Quarts de finale',
            $round === $lastRound - 3 => 'Huitièmes de finale',
            default => 'Tour '.$round,
        };
    }
}
