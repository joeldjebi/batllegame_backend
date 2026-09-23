<?php

namespace App\Jobs;

use App\Models\Group;
use App\Services\Competition\GroupStandingsCalculator;
use App\Services\Competition\PhaseProgress;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recomputes the standings of a group after one of its matches closed.
 * Unique per group: bursts of closures collapse into one recalculation.
 */
class RecalculateGroupStandings implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Group $group) {}

    public function uniqueId(): string
    {
        return (string) $this->group->id;
    }

    public function handle(GroupStandingsCalculator $standings, PhaseProgress $progress): void
    {
        $standings->recalculate($this->group);
        $progress->finishIfComplete($this->group->phase);
    }
}
