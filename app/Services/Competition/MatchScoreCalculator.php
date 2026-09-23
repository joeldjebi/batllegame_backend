<?php

namespace App\Services\Competition;

use App\Models\BattleMatch;

/**
 * Computes the scores of a match from the source tables (jury_scores, public_votes).
 *
 *  - jury score (0-100): for each judge, weighted average of the criteria
 *    normalized by their max points; then the mean over the judges who scored.
 *  - public score (0-100): share of the match votes; 50/50 when nobody voted.
 *  - final score: jury and public combined with the phase weights.
 */
class MatchScoreCalculator
{
    /**
     * @return array<int, array{jury: ?float, public: ?float, final: ?float}> Keyed by participant id.
     */
    public function calculate(BattleMatch $match): array
    {
        $rules = $match->phase->rules;
        $participantIds = $match->slots()->whereNotNull('participant_id')->orderBy('slot')->pluck('participant_id')->all();

        $jury = $this->juryScores($match, $participantIds);
        $public = $this->publicScores($match, $participantIds);

        $scores = [];

        foreach ($participantIds as $id) {
            $juryScore = $rules->usesJury() ? $jury[$id] : null;
            $publicScore = $rules->usesPublic() ? $public[$id] : null;

            $final = ($rules->usesJury() && $juryScore === null)
                ? null
                : round(($juryScore ?? 0) * $rules->juryWeight / 100 + ($publicScore ?? 0) * $rules->publicWeight / 100, 2);

            $scores[$id] = ['jury' => $juryScore, 'public' => $publicScore, 'final' => $final];
        }

        return $scores;
    }

    /**
     * @param  list<int>  $participantIds
     * @return array<int, ?float>
     */
    private function juryScores(BattleMatch $match, array $participantIds): array
    {
        $criteria = $match->competition->criteria()->get()->keyBy('id');

        $rows = $match->juryScores()
            ->whereIn('participant_id', $participantIds)
            ->get(['participant_id', 'judge_id', 'criterion_id', 'score']);

        $result = [];

        foreach ($participantIds as $participantId) {
            $perJudge = $rows->where('participant_id', $participantId)
                ->groupBy('judge_id')
                ->map(function ($judgeRows) use ($criteria): ?float {
                    $weighted = 0.0;
                    $weights = 0.0;

                    foreach ($judgeRows as $row) {
                        $criterion = $criteria->get($row->criterion_id);

                        if ($criterion === null || $criterion->max_points <= 0) {
                            continue;
                        }

                        $weighted += $criterion->weight * ($row->score / $criterion->max_points);
                        $weights += $criterion->weight;
                    }

                    return $weights > 0 ? $weighted / $weights * 100 : null;
                })
                ->filter(fn (?float $score) => $score !== null);

            $result[$participantId] = $perJudge->isEmpty() ? null : round($perJudge->avg(), 2);
        }

        return $result;
    }

    /**
     * @param  list<int>  $participantIds
     * @return array<int, float>
     */
    private function publicScores(BattleMatch $match, array $participantIds): array
    {
        $counts = $match->publicVotes()
            ->whereIn('participant_id', $participantIds)
            ->selectRaw('participant_id, count(*) as votes')
            ->groupBy('participant_id')
            ->pluck('votes', 'participant_id');

        $total = (int) $counts->sum();
        $result = [];

        foreach ($participantIds as $participantId) {
            $result[$participantId] = $total === 0
                ? round(100 / max(count($participantIds), 1), 2)
                : round((int) ($counts[$participantId] ?? 0) / $total * 100, 2);
        }

        return $result;
    }
}
