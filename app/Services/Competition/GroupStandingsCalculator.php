<?php

namespace App\Services\Competition;

use App\Enums\MatchStatus;
use App\Models\Group;
use Illuminate\Support\Facades\DB;

/**
 * Copies the ranking of a closed group (its single match, see MatchCloser::rankGroup)
 * into group_participants.rank, read by the qualification. Fully idempotent.
 * A forfeited artist, or a group not closed yet, has no rank.
 */
class GroupStandingsCalculator
{
    public function recalculate(Group $group): void
    {
        $match = $group->matches()->where('status', MatchStatus::Closed)->with('slots')->first();
        $ranks = $match ? $match->slots->whereNotNull('participant_id')->pluck('rank', 'participant_id') : collect();

        DB::transaction(function () use ($group, $ranks): void {
            foreach ($group->standings()->get() as $row) {
                $row->update(['rank' => $ranks[$row->participant_id] ?? null]);
            }
        });
    }
}
