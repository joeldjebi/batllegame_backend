<?php

namespace App\Listeners;

use App\Events\MatchClosed;
use App\Services\Competition\BracketAdvancer;
use App\Services\Competition\PhaseProgress;

/**
 * Elimination matches: move the winner (and the loser in double elimination) forward.
 */
class AdvanceBracket
{
    public function __construct(
        private BracketAdvancer $advancer,
        private PhaseProgress $progress,
    ) {}

    public function handle(MatchClosed $event): void
    {
        if ($event->match->isGroupMatch()) {
            return;
        }

        $this->advancer->advance($event->match);
        $this->progress->finishIfComplete($event->match->phase);
    }
}
