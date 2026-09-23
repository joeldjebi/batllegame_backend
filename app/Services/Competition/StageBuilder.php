<?php

namespace App\Services\Competition;

use App\Enums\BracketSide;
use App\Enums\PhaseType;
use App\Models\Phase;

/**
 * Creates the stages of a phase once its matches are generated: one stage for
 * a group phase, one stage per bracket round for elimination phases.
 */
class StageBuilder
{
    public function build(Phase $phase): void
    {
        $matches = $phase->matches()->get();

        if ($phase->type === PhaseType::Groups) {
            $stage = $phase->stages()->create(['number' => 1, 'name' => 'Poules']);
            $phase->matches()->update(['stage_id' => $stage->id]);

            return;
        }

        $double = $phase->type === PhaseType::DoubleElimination;
        $order = [BracketSide::Winners->value => 0, BracketSide::Losers->value => 1, BracketSide::GrandFinal->value => 2];
        $number = 0;

        $matches->groupBy(fn ($m) => $m->bracket?->value)
            ->sortBy(fn ($group, $side) => $order[$side] ?? 9)
            ->each(function ($sideMatches, $side) use ($phase, $double, &$number): void {
                $bracket = BracketSide::tryFrom((string) $side);
                $lastRound = $sideMatches->max('round');

                foreach ($sideMatches->groupBy('round')->sortKeys() as $round => $roundMatches) {
                    $name = RoundLabel::for($bracket, $round, $lastRound);
                    if ($double && $bracket === BracketSide::Winners) {
                        $name = 'Principal · '.$name;
                    }

                    $stage = $phase->stages()->create(['number' => ++$number, 'name' => $name]);
                    $phase->matches()->whereKey($roundMatches->modelKeys())->update(['stage_id' => $stage->id]);
                }
            });
    }
}
