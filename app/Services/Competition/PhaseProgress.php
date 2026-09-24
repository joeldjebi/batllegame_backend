<?php

namespace App\Services\Competition;

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Models\Phase;
use Illuminate\Support\Facades\DB;

/**
 * Finishes an elimination phase once all its matches are decided (and the competition
 * with its last one). A group phase finishes when the organizer publishes its results
 * (GroupResultsService), after the jury deliberation.
 */
class PhaseProgress
{
    public function __construct(private StageService $stageService) {}

    public function finishIfComplete(Phase $phase): bool
    {
        $this->stageService->refreshPhase($phase);

        return DB::transaction(function () use ($phase): bool {
            $phase = Phase::query()->lockForUpdate()->findOrFail($phase->id);

            if ($phase->status !== PhaseStatus::InProgress || $phase->type === PhaseType::Groups) {
                return false;
            }

            $undecided = $phase->matches()
                ->whereNotIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])
                ->exists();

            if ($undecided) {
                return false;
            }

            $phase->forceFill(['status' => PhaseStatus::Finished, 'finished_at' => now()])->save();

            // The competition ends with its last elimination phase.
            if ($phase->nextPhase() === null) {
                $phase->competition->update(['status' => CompetitionStatus::Finished]);
            }

            return true;
        });
    }
}
