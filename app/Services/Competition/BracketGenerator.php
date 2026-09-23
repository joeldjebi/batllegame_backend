<?php

namespace App\Services\Competition;

use App\Enums\BracketSide;
use App\Enums\PhaseType;
use App\Models\BattleMatch;
use App\Models\Participant;
use App\Models\Phase;
use Illuminate\Support\Collection;

/**
 * Builds the complete elimination bracket when a phase starts.
 *
 * Every match is created up front with two (possibly empty) slots and its
 * next_match_id / next_match_slot (winner) and, in double elimination,
 * loser_next_match_id / loser_next_match_slot. Byes go to the best seeds and
 * are resolved immediately as walkovers.
 *
 * Double elimination layout for a bracket of size S = 2^k:
 *  - winners bracket: rounds 1..k
 *  - losers bracket: rounds 1..2(k-1); odd rounds pair survivors, even rounds
 *    receive the losers of winners round (r/2 + 1)
 *  - grand final: winners champion (slot 1) vs losers champion (slot 2),
 *    plus an optional reset match
 */
class BracketGenerator
{
    public function __construct(private BracketAdvancer $advancer) {}

    /**
     * @param  Collection<int, Participant>  $entrants  Ordered by seed (best first).
     */
    public function generate(Phase $phase, Collection $entrants): void
    {
        $entrants = $entrants->values();
        $size = SeedOrder::bracketSize($entrants->count());
        $k = (int) log($size, 2);
        $double = $phase->type === PhaseType::DoubleElimination;

        // Matches are created from the end of the bracket backwards so that
        // every next_match_id already exists when its feeder is inserted.
        $grandFinal = $double ? $this->createGrandFinal($phase) : null;
        $losers = $double ? $this->createLosersBracket($phase, $size, $k, $grandFinal) : [];
        $winners = $this->createWinnersBracket($phase, $size, $k, $grandFinal, $losers);

        $this->seedFirstRound($winners[1], $entrants, $size);

        foreach ($winners[1] as $match) {
            $this->advancer->resolveIfReady($match);
        }
    }

    private function createGrandFinal(Phase $phase): BattleMatch
    {
        $reset = $phase->rules->grandFinalReset
            ? $this->createMatch($phase, BracketSide::GrandFinal, 2, 1)
            : null;

        // In a reset final both players move on: the rematch only happens if the
        // losers bracket champion (slot 2) wins the first final.
        return $this->createMatch($phase, BracketSide::GrandFinal, 1, 1, [
            'next_match_id' => $reset?->id,
            'next_match_slot' => $reset ? 1 : null,
            'loser_next_match_id' => $reset?->id,
            'loser_next_match_slot' => $reset ? 2 : null,
        ]);
    }

    /**
     * @return array<int, array<int, BattleMatch>> [round][position]
     */
    private function createLosersBracket(Phase $phase, int $size, int $k, BattleMatch $grandFinal): array
    {
        $rounds = 2 * ($k - 1);
        $matches = [];

        for ($round = $rounds; $round >= 1; $round--) {
            // Rounds 2j-1 and 2j both hold S / 2^(j+1) matches.
            $count = intdiv($size, 2 ** (intdiv($round + 1, 2) + 1));

            for ($position = 1; $position <= $count; $position++) {
                if ($round === $rounds) {
                    $next = ['next_match_id' => $grandFinal->id, 'next_match_slot' => 2];
                } elseif ($round % 2 === 1) {
                    // Odd round winner meets a winners-bracket dropout in the same position.
                    $next = ['next_match_id' => $matches[$round + 1][$position]->id, 'next_match_slot' => 1];
                } else {
                    $next = [
                        'next_match_id' => $matches[$round + 1][(int) ceil($position / 2)]->id,
                        'next_match_slot' => $position % 2 === 1 ? 1 : 2,
                    ];
                }

                $matches[$round][$position] = $this->createMatch($phase, BracketSide::Losers, $round, $position, $next);
            }
        }

        return $matches;
    }

    /**
     * @param  array<int, array<int, BattleMatch>>  $losers
     * @return array<int, array<int, BattleMatch>> [round][position]
     */
    private function createWinnersBracket(Phase $phase, int $size, int $k, ?BattleMatch $grandFinal, array $losers): array
    {
        $matches = [];

        for ($round = $k; $round >= 1; $round--) {
            $count = intdiv($size, 2 ** $round);

            for ($position = 1; $position <= $count; $position++) {
                $attributes = $round === $k
                    ? ['next_match_id' => $grandFinal?->id, 'next_match_slot' => $grandFinal ? 1 : null]
                    : [
                        'next_match_id' => $matches[$round + 1][(int) ceil($position / 2)]->id,
                        'next_match_slot' => $position % 2 === 1 ? 1 : 2,
                    ];

                if ($grandFinal !== null) {
                    $attributes += $this->loserDestination($round, $position, $count, $k, $grandFinal, $losers);
                }

                $matches[$round][$position] = $this->createMatch($phase, BracketSide::Winners, $round, $position, $attributes);
            }
        }

        return $matches;
    }

    /**
     * Where the loser of a winners-bracket match drops.
     *
     * @param  array<int, array<int, BattleMatch>>  $losers
     * @return array{loser_next_match_id: int, loser_next_match_slot: int}
     */
    private function loserDestination(int $round, int $position, int $count, int $k, BattleMatch $grandFinal, array $losers): array
    {
        // Two-player bracket: no losers bracket, the loser goes straight to the grand final.
        if ($k === 1) {
            return ['loser_next_match_id' => $grandFinal->id, 'loser_next_match_slot' => 2];
        }

        if ($round === 1) {
            return [
                'loser_next_match_id' => $losers[1][(int) ceil($position / 2)]->id,
                'loser_next_match_slot' => $position % 2 === 1 ? 1 : 2,
            ];
        }

        // Reverse the drop order every other round to delay rematches.
        $target = $round % 2 === 0 ? $count - $position + 1 : $position;

        return [
            'loser_next_match_id' => $losers[2 * ($round - 1)][$target]->id,
            'loser_next_match_slot' => 2,
        ];
    }

    /**
     * @param  array<int, BattleMatch>  $firstRound
     * @param  Collection<int, Participant>  $entrants
     */
    private function seedFirstRound(array $firstRound, Collection $entrants, int $size): void
    {
        $order = SeedOrder::positions($size);

        foreach ($firstRound as $position => $match) {
            foreach ([1, 2] as $slot) {
                $seed = $order[($position - 1) * 2 + $slot - 1];
                $participant = $entrants->get($seed - 1); // null = bye

                if ($participant !== null) {
                    $match->slots()->where('slot', $slot)->update(['participant_id' => $participant->id]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMatch(Phase $phase, BracketSide $bracket, int $round, int $position, array $attributes = []): BattleMatch
    {
        $match = new BattleMatch([
            'phase_id' => $phase->id,
            'bracket' => $bracket,
            'round' => $round,
            'bracket_position' => $position,
            ...$attributes,
        ]);
        $match->save();

        $match->slots()->createMany([['slot' => 1], ['slot' => 2]]);

        return $match;
    }
}
