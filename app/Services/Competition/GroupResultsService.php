<?php

namespace App\Services\Competition;

use App\Enums\MatchStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Exceptions\CompetitionFlowException;
use App\Models\Participant;
use App\Models\Phase;
use App\Realtime\Channel;
use App\Realtime\Realtime;
use Illuminate\Support\Facades\DB;

/**
 * The organizer publishes the results of a group phase once every group is closed
 * (end of the jury deliberation): the top of each group qualifies, the others are
 * eliminated, the ranking becomes public and the next phase can start.
 */
class GroupResultsService
{
    public function __construct(
        private GroupStandingsCalculator $standings,
        private QualificationService $qualification,
        private Realtime $realtime,
    ) {}

    public function publish(Phase $phase): Phase
    {
        $phase = DB::transaction(function () use ($phase): Phase {
            $phase = Phase::query()->lockForUpdate()->findOrFail($phase->id);

            if ($phase->type !== PhaseType::Groups || $phase->status !== PhaseStatus::InProgress) {
                throw CompetitionFlowException::groupResultsPublished();
            }

            $open = $phase->matches()->whereNotIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])->count();
            if ($open > 0) {
                throw CompetitionFlowException::groupsNotClosed($open);
            }

            $phase->groups->each(fn ($group) => $this->standings->recalculate($group));
            $this->qualification->eliminateNonQualifiers($phase);

            $phase->forceFill(['status' => PhaseStatus::Finished, 'finished_at' => now(), 'results_published_at' => now()])->save();

            return $phase;
        });

        $qualified = $this->qualification->qualifiers($phase)->pluck('id')->all();
        $users = Participant::query()->whereIn('id', $phase->groups->flatMap->participants->pluck('id'))->get(['id', 'user_id']);

        $this->realtime->push([Channel::competition($phase->competition_id), Channel::backOffice($phase->competition_id)], 'phase.published', ['competition_id' => $phase->competition_id, 'phase_id' => $phase->id]);
        foreach ($users as $participant) {
            $this->realtime->push([Channel::user($participant->user_id)], 'phase.published', ['competition_id' => $phase->competition_id, 'phase_id' => $phase->id],
                in_array($participant->id, $qualified, true) ? 'Qualifié : tu passes à la phase suivante.' : 'Résultats des poules publiés : tu n\'es pas qualifié cette fois.');
        }

        return $phase;
    }
}
