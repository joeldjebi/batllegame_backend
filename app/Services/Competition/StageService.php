<?php

namespace App\Services\Competition;

use App\Enums\CompetitionMode;
use App\Enums\MatchStatus;
use App\Enums\PerformanceStatus;
use App\Enums\StageStatus;
use App\Exceptions\CompetitionFlowException;
use App\Models\BattleMatch;
use App\Models\Phase;
use App\Models\Stage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Drives a stage through its lifecycle.
 *
 * Online:  Pending -> Submissions (until the deadline) -> forfeits for missing
 *          submissions -> Voting (jury + public on the videos, then the jury alone
 *          during the deliberation) -> Closed.
 * On-site: Pending -> Voting (the organizer opens each match live) -> Closed.
 */
class StageService
{
    public function __construct(private MatchCloser $closer) {}

    /**
     * @param  array{submission_deadline?: ?string, voting_opens_at?: ?string, voting_closes_at?: ?string, deliberation_minutes?: int}  $dates
     */
    public function schedule(Stage $stage, array $dates): Stage
    {
        if ($stage->status === StageStatus::Closed) {
            throw CompetitionFlowException::stageClosed();
        }

        $stage->fill($dates)->save();

        // Keep the voting window and the deliberation of matches already open in sync.
        $stage->matches()->where('status', MatchStatus::Voting)->update([
            'voting_closes_at' => $stage->voting_closes_at,
            'deliberation_ends_at' => $stage->deliberationEndFor($stage->voting_closes_at),
        ]);

        return $stage;
    }

    public function openSubmissions(Stage $stage): Stage
    {
        return DB::transaction(function () use ($stage): Stage {
            $stage = Stage::query()->lockForUpdate()->findOrFail($stage->id);

            if (! $stage->isOnline()) {
                throw CompetitionFlowException::stageNotOnline();
            }

            if ($stage->status !== StageStatus::Pending) {
                throw CompetitionFlowException::stageNotPending();
            }

            if ($stage->submission_deadline === null || $stage->submission_deadline->isPast()) {
                throw CompetitionFlowException::deadlineRequired();
            }

            $this->ensureParticipantsKnown($stage);

            $stage->forceFill(['status' => StageStatus::Submissions])->save();
            $stage->playableMatches()->update([
                'status' => MatchStatus::Submissions,
                'submission_deadline' => $stage->submission_deadline,
            ]);

            return $stage;
        });
    }

    /**
     * Missing (or rejected) submission at the deadline = forfeit. Idempotent.
     *
     * @return int Number of forfeited matches.
     */
    public function applyForfeits(Stage $stage): int
    {
        $stage->refresh();

        if ($stage->forfeits_applied_at !== null || $stage->status !== StageStatus::Submissions || ! $stage->isDeadlinePassed()) {
            return 0;
        }

        $submitted = $stage->performances()
            ->where('status', '!=', PerformanceStatus::Rejected)
            ->pluck('participant_id')
            ->all();

        $forfeits = 0;

        foreach ($stage->playableMatches()->with('slots')->get() as $match) {
            $ids = $match->slots->pluck('participant_id')->filter()->values()->all();
            $present = array_values(array_intersect($ids, $submitted));

            if (count($present) === 2) {
                continue;
            }

            $this->closer->forfeit($match, $present[0] ?? null);
            $forfeits++;
        }

        $stage->forceFill(['forfeits_applied_at' => now()])->save();

        return $forfeits;
    }

    public function openVoting(Stage $stage): Stage
    {
        $stage->refresh();

        if (! in_array($stage->status, [StageStatus::Pending, StageStatus::Submissions], true)) {
            throw CompetitionFlowException::stageNotPending();
        }

        if ($stage->isOnline()) {
            if ($stage->status !== StageStatus::Submissions || ! $stage->isDeadlinePassed()) {
                throw CompetitionFlowException::deadlineNotReached();
            }

            $this->applyForfeits($stage);

            $unreviewed = $stage->performances()
                ->whereIn('status', [PerformanceStatus::Processing, PerformanceStatus::Pending])
                ->whereIn('participant_id', $stage->participantIds())
                ->count();

            if ($unreviewed > 0) {
                throw CompetitionFlowException::submissionsToReview($unreviewed);
            }
        } else {
            $this->ensureParticipantsKnown($stage);
        }

        DB::transaction(function () use ($stage): void {
            foreach ($stage->playableMatches()->get() as $match) {
                $this->openMatchVoting($match, $stage->voting_closes_at);
            }

            $stage->forceFill(['status' => StageStatus::Voting])->save();
        });

        return $this->closeIfComplete($stage);
    }

    /**
     * Open the vote of one match (on-site: the organizer does it when the battle
     * is over on stage). Generates the room code when the competition requires it.
     * The jury keeps scoring $deliberationMinutes (default: the stage's) after the vote.
     */
    public function openMatchVoting(BattleMatch $match, ?Carbon $closesAt = null, ?int $deliberationMinutes = null): BattleMatch
    {
        $minutes = $deliberationMinutes ?? $match->stage?->deliberation_minutes ?? 0;

        $phase = $match->phase;
        $onSite = $phase->effectiveMode() === CompetitionMode::OnSite;

        $match->forceFill([
            'status' => MatchStatus::Voting,
            'voting_opens_at' => now(),
            'voting_closes_at' => $closesAt,
            'deliberation_ends_at' => $closesAt && $minutes > 0 ? $closesAt->copy()->addMinutes($minutes) : null,
            'vote_code' => $onSite && $phase->competition->settings->onsiteVoteCode
                ? str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT)
                : null,
        ])->save();

        if ($match->stage && $match->stage->status === StageStatus::Pending) {
            $match->stage->forceFill(['status' => StageStatus::Voting])->save();
        }

        return $match;
    }

    public function closeIfComplete(Stage $stage): Stage
    {
        $undecided = $stage->matches()->whereNotIn('status', [MatchStatus::Closed, MatchStatus::Cancelled])->exists();

        if (! $undecided && $stage->status !== StageStatus::Closed) {
            $stage->forceFill(['status' => StageStatus::Closed])->save();
        }

        return $stage;
    }

    /**
     * Close every finished stage of a phase (walkovers do not fire events).
     */
    public function refreshPhase(Phase $phase): void
    {
        $phase->stages()->where('status', '!=', StageStatus::Closed)->get()->each(fn (Stage $stage) => $this->closeIfComplete($stage));
    }

    /**
     * Scheduler: forfeits at the deadline, then voting at the planned time.
     *
     * @return array{forfeits: int, opened: int}
     */
    public function processDue(): array
    {
        $forfeits = 0;
        $opened = 0;

        $due = Stage::query()
            ->where('status', StageStatus::Submissions)
            ->where('submission_deadline', '<=', now())
            ->get();

        foreach ($due as $stage) {
            $forfeits += $this->applyForfeits($stage);

            if ($stage->voting_opens_at !== null && $stage->voting_opens_at->isFuture()) {
                continue;
            }

            try {
                $this->openVoting($stage);
                $opened++;
            } catch (CompetitionFlowException $e) {
                Log::info("Stage #{$stage->id} voting not opened yet: {$e->getMessage()}");
            }
        }

        return ['forfeits' => $forfeits, 'opened' => $opened];
    }

    private function ensureParticipantsKnown(Stage $stage): void
    {
        $waiting = $stage->matches()
            ->where('status', MatchStatus::Scheduled)
            ->whereHas('slots', fn ($q) => $q->whereNull('participant_id'))
            ->exists();

        if ($waiting) {
            throw CompetitionFlowException::stageParticipantsUnknown();
        }
    }
}
