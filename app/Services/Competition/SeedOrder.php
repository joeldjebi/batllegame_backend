<?php

namespace App\Services\Competition;

use InvalidArgumentException;

/**
 * Pure helpers for bracket seeding.
 */
final class SeedOrder
{
    /**
     * Smallest power of two that can hold $count entrants.
     */
    public static function bracketSize(int $count): int
    {
        if ($count < 2) {
            throw new InvalidArgumentException('A bracket needs at least 2 entrants.');
        }

        return 2 ** (int) ceil(log($count, 2));
    }

    /**
     * Seeds in bracket order so that 1 and 2 can only meet in the final,
     * e.g. size 8 => [1, 8, 4, 5, 2, 7, 3, 6]. Consecutive pairs are round 1 matches.
     *
     * @return list<int>
     */
    public static function positions(int $size): array
    {
        if ($size < 2 || ($size & ($size - 1)) !== 0) {
            throw new InvalidArgumentException('The bracket size must be a power of two.');
        }

        $order = [1];

        while (count($order) < $size) {
            $sum = count($order) * 2 + 1;
            $next = [];

            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $sum - $seed;
            }

            $order = $next;
        }

        return $order;
    }
}
