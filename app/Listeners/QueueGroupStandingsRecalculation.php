<?php

namespace App\Listeners;

use App\Events\MatchClosed;
use App\Jobs\RecalculateGroupStandings;

/**
 * Group matches: recompute the group standings in the queue.
 */
class QueueGroupStandingsRecalculation
{
    public function handle(MatchClosed $event): void
    {
        if ($event->match->isGroupMatch()) {
            RecalculateGroupStandings::dispatch($event->match->group);
        }
    }
}
