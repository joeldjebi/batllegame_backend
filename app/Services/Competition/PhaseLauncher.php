<?php

namespace App\Services\Competition;

use App\Enums\CompetitionMode;
use App\Enums\CompetitionStatus;
use App\Enums\ParticipantStatus;
use App\Enums\PhaseStatus;
use App\Enums\PhaseType;
use App\Exceptions\CompetitionFlowException;
use App\Models\Participant;
use App\Models\Phase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Starts a phase: freezes its rules and generates its groups or bracket.
 */
class PhaseLauncher
{
    public function __construct(
        private GroupDrawService $groups,
        private BracketGenerator $brackets,
        private QualificationService $qualification,
        private PhaseProgress $progress,
        private StageBuilder $stages,
        private PhaseCalendar $calendar,
        private StageService $stageService,
    ) {}

    /**
     * @throws CompetitionFlowException
     */
    public function start(Phase $phase): Phase
    {
        return DB::transaction(function () use ($phase): Phase {
            $phase = Phase::query()->lockForUpdate()->findOrFail($phase->id);
            $competition = $phase->competition;

            if ($phase->status !== PhaseStatus::Pending) {
                throw CompetitionFlowException::phaseNotPending();
            }

            if (! in_array($competition->status, [CompetitionStatus::Registration, CompetitionStatus::InProgress], true)) {
                throw CompetitionFlowException::competitionNotRunning();
            }

            // Each phase is played either online or on site.
            if ($phase->effectiveMode() === CompetitionMode::Hybrid) {
                throw CompetitionFlowException::hybridPhaseMode();
            }

            // With a pre-selection, only the published selection competes.
            if ($competition->preselection && $competition->preselection->published_at === null) {
                throw CompetitionFlowException::preselectionNotPublished();
            }

            $entrants = $this->entrants($phase);
            $this->ensureEnoughEntrants($phase, $entrants);

            $phase->markAsStarted();

            if ($competition->status === CompetitionStatus::Registration) {
                $competition->update(['status' => CompetitionStatus::InProgress]);
            }

            match ($phase->type) {
                PhaseType::Groups => $this->groups->draw($phase, $entrants),
                PhaseType::SingleElimination, PhaseType::DoubleElimination => $this->brackets->generate($phase, $entrants),
            };

            $this->stages->build($phase);
            // Dates planned before the start (« Calendrier prévu ») go to the real stages.
            $this->calendar->apply($phase, $this->stageService);

            // A bracket made only of byes may already be decided.
            $this->progress->finishIfComplete($phase);

            return $phase->refresh();
        });
    }

    /**
     * First phase: validated participants by seed. Later phases: qualifiers of the previous one.
     *
     * @return Collection<int, Participant>
     */
    private function entrants(Phase $phase): Collection
    {
        $previous = Phase::query()
            ->where('competition_id', $phase->competition_id)
            ->where('position', '<', $phase->position)
            ->orderByDesc('position')
            ->first();

        if ($previous === null) {
            return $phase->competition->participants()
                ->where('status', ParticipantStatus::Validated)
                ->orderByRaw('seed IS NULL')
                ->orderBy('seed')
                ->orderBy('id')
                ->get();
        }

        if ($previous->status !== PhaseStatus::Finished) {
            throw CompetitionFlowException::previousPhaseNotFinished();
        }

        return $this->qualification->qualifiers($previous);
    }

    /**
     * @param  Collection<int, Participant>  $entrants
     */
    private function ensureEnoughEntrants(Phase $phase, Collection $entrants): void
    {
        if ($phase->type !== PhaseType::Groups) {
            if ($entrants->count() < 2) {
                throw CompetitionFlowException::notEnoughEntrants(2, $entrants->count());
            }

            return;
        }

        // Same rules as the back-office preview (GroupPlan): each group plays and qualifies fewer than its members.
        $problems = GroupPlan::problems($entrants->count(), (int) $phase->rules->groupCount, $phase->qualifiers_per_group ?? 1);

        if ($problems !== []) {
            throw CompetitionFlowException::groupFormat($problems[0]);
        }
    }
}
