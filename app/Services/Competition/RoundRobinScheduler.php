<?php

namespace App\Services\Competition;

/**
 * Pure round-robin scheduling (circle method): everyone meets everyone once,
 * and nobody plays twice in the same round.
 */
final class RoundRobinScheduler
{
    /**
     * @template T
     *
     * @param  list<T>  $entrants
     * @return list<list<array{0: T, 1: T}>> Rounds, each a list of pairs.
     */
    public static function rounds(array $entrants): array
    {
        $entrants = array_values($entrants);

        if (count($entrants) < 2) {
            return [];
        }

        // Odd count: a null "bye" entrant rests one player per round.
        if (count($entrants) % 2 === 1) {
            $entrants[] = null;
        }

        $count = count($entrants);
        $rounds = [];

        for ($round = 0; $round < $count - 1; $round++) {
            $pairs = [];

            for ($i = 0; $i < $count / 2; $i++) {
                $home = $entrants[$i];
                $away = $entrants[$count - 1 - $i];

                if ($home !== null && $away !== null) {
                    // Alternate slots so the fixed entrant is not always slot 1.
                    $pairs[] = ($round % 2 === 0 || $i > 0) ? [$home, $away] : [$away, $home];
                }
            }

            $rounds[] = $pairs;

            // Keep the first entrant fixed and rotate the others clockwise.
            $fixed = array_shift($entrants);
            array_unshift($entrants, array_pop($entrants));
            array_unshift($entrants, $fixed);
        }

        return $rounds;
    }
}
