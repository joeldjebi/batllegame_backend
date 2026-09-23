<?php

namespace App\Services\Competition;

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Models\Phase;
use Illuminate\Support\Facades\DB;

/**
 * Finishes a phase once all its matches are decided, and the competition
 * once its last elimination phase is over.
 */
class PhaseProgress
{
    public function __construct(
        private GroupStandingsCalculator $standings,
        private QualificationService $qualification,
    ) {}

    public function finishIfComplete(Phase $phase): bool
    {
        return DB::transaction(function () use ($phase): bool {
            $phase = Phase::query()->lockForUpdate()->findOrFail($phase->id);

            if ($phase->status !== PhaseStatus::InProgress) {
                return false;
            }

            $undecided = $phase->matches()
                ->whereNotIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])
                ->exists();

            if ($undecided) {
                return false;
            }

            if ($phase->type === PhaseType::Groups) {
                // Final, synchronous standings: queued recalculations may still be pending.
                $phase->groups->each(fn ($group) => $this->standings->recalculate($group));
                $this->qualification->eliminateNonQualifiers($phase);
            }

            $phase->forceFill(['status' => PhaseStatus::Finished, 'finished_at' => now()])->save();

            // A group phase only produces qualifiers: the organizer may still add the
            // next phase. The competition ends with its last elimination phase.
            if ($phase->type !== PhaseType::Groups && $phase->nextPhase() === null) {
                $phase->competition->update(['status' => CompetitionStatus::Finished]);
            }

            return true;
        });
    }
}
