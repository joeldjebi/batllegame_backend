<?php

namespace App\Services\Competition;

use App\Enums\MatchStatus;
use App\Enums\ParticipantStatus;
use App\Enums\TieBreaker;
use App\Events\MatchClosed;
use App\Exceptions\CompetitionFlowException;
use App\Models\BattleMatch;
use App\Models\Participant;
use Illuminate\Support\Facades\DB;

/**
 * Closes a played match: stores the denormalized scores, decides the winner
 * and fires MatchClosed (after commit) for bracket advancement / standings.
 */
class MatchCloser
{
    public function __construct(private MatchScoreCalculator $calculator) {}

    /**
     * @param  int|null  $forcedWinnerId  Only used to break a perfect tie.
     *
     * @throws CompetitionFlowException
     */
    public function close(BattleMatch $match, ?int $forcedWinnerId = null): BattleMatch
    {
        return DB::transaction(function () use ($match, $forcedWinnerId): BattleMatch {
            $match = BattleMatch::query()->lockForUpdate()->findOrFail($match->id);

            if ($match->status === MatchStatus::Closed) {
                return $match; // Idempotent: the scheduler and an organizer may race.
            }

            $playable = in_array($match->status, [MatchStatus::Scheduled, MatchStatus::Submissions, MatchStatus::Voting], true)
                && $match->slots()->whereNotNull('participant_id')->count() === 2;

            if (! $playable) {
                throw CompetitionFlowException::matchNotPlayable();
            }

            $scores = $this->storeScores($match);

            if (in_array(null, array_column($scores, 'final'), true)) {
                throw CompetitionFlowException::missingJuryScores();
            }

            $match->forceFill([
                'status' => MatchStatus::Closed,
                'winner_id' => $this->decideWinner($match, $scores, $forcedWinnerId),
                'closed_at' => now(),
            ])->save();

            MatchClosed::dispatch($match);

            return $match;
        });
    }

    /**
     * Decide a match without playing it (missing submission).
     * $winnerId null = both forfeited: void in elimination (nobody advances),
     * a double loss in groups.
     */
    public function forfeit(BattleMatch $match, ?int $winnerId): BattleMatch
    {
        return DB::transaction(function () use ($match, $winnerId): BattleMatch {
            $match = BattleMatch::query()->lockForUpdate()->findOrFail($match->id);

            if (in_array($match->status, [MatchStatus::Closed, MatchStatus::Cancelled], true)) {
                return $match;
            }

            $void = $winnerId === null && ! $match->isGroupMatch();

            $match->forceFill([
                'status' => $void ? MatchStatus::Cancelled : MatchStatus::Closed,
                'winner_id' => $winnerId,
                'is_forfeit' => true,
                'closed_at' => now(),
            ])->save();

            if ($void) {
                Participant::query()
                    ->whereIn('id', $match->slots()->whereNotNull('participant_id')->select('participant_id'))
                    ->update(['status' => ParticipantStatus::Withdrawn]);
            }

            MatchClosed::dispatch($match);

            return $match;
        });
    }

    /**
     * Recompute and store the denormalized scores of the match slots.
     *
     * @return array<int, array{jury: ?float, public: ?float, final: ?float}>
     */
    public function storeScores(BattleMatch $match): array
    {
        $scores = $this->calculator->calculate($match);

        foreach ($scores as $participantId => $score) {
            $match->slots()->where('participant_id', $participantId)->update([
                'jury_score' => $score['jury'],
                'public_score' => $score['public'],
                'final_score' => $score['final'],
            ]);
        }

        return $scores;
    }

    /**
     * @param  array<int, array{jury: ?float, public: ?float, final: ?float}>  $scores
     * @return int|null Winner participant id, null for a draw.
     */
    private function decideWinner(BattleMatch $match, array $scores, ?int $forcedWinnerId): ?int
    {
        [$a, $b] = array_keys($scores);
        $rules = $match->phase->rules;

        $comparison = $scores[$a]['final'] <=> $scores[$b]['final'];

        if ($comparison === 0 && $match->isGroupMatch() && $rules->allowDraws) {
            return null;
        }

        foreach ($comparison === 0 ? $rules->tieBreakers : [] as $tieBreaker) {
            $comparison = match ($tieBreaker) {
                TieBreaker::JuryScore => ($scores[$a]['jury'] ?? 0) <=> ($scores[$b]['jury'] ?? 0),
                TieBreaker::PublicScore => ($scores[$a]['public'] ?? 0) <=> ($scores[$b]['public'] ?? 0),
                // Lower seed number is better; unseeded participants come last.
                TieBreaker::Seed => $this->seedRank($b) <=> $this->seedRank($a),
                // Only meaningful for group standings.
                TieBreaker::HeadToHead, TieBreaker::ScoreDiff => 0,
            };

            if ($comparison !== 0) {
                break;
            }
        }

        if ($comparison !== 0) {
            return $comparison > 0 ? $a : $b;
        }

        if ($forcedWinnerId === null) {
            throw CompetitionFlowException::unresolvedTie();
        }

        if (! in_array($forcedWinnerId, [$a, $b], true)) {
            throw CompetitionFlowException::invalidForcedWinner();
        }

        return $forcedWinnerId;
    }

    private function seedRank(int $participantId): int
    {
        return Participant::query()->whereKey($participantId)->value('seed') ?? PHP_INT_MAX;
    }
}
