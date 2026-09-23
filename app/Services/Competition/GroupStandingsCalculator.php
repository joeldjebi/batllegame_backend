<?php

namespace App\Services\Competition;

use App\Enums\MatchStatus;
use App\Enums\TieBreaker;
use App\Models\BattleMatch;
use App\Models\Group;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes group_participants (points, record, score difference, rank)
 * from the closed matches of the group. Fully idempotent.
 *
 * Ranking: points, then the phase tie breakers in order (head-to-head is the
 * mini-league among the tied participants), then participant id.
 */
class GroupStandingsCalculator
{
    public function recalculate(Group $group): void
    {
        $rules = $group->phase->rules;

        $matches = $group->matches()
            ->where('status', MatchStatus::Closed)
            ->with('slots')
            ->get();

        $rows = $group->standings()->with('participant:id,seed')->get()->keyBy('participant_id');
        $stats = $rows->map(fn ($row) => [
            'participant_id' => $row->participant_id,
            'seed' => $row->participant->seed,
            'points' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
            'score_diff' => 0.0, 'jury' => 0.0, 'public' => 0.0,
        ])->all();

        foreach ($matches as $match) {
            foreach ($this->outcomes($match) as $id => $outcome) {
                if (! isset($stats[$id])) {
                    continue;
                }

                $stats[$id]['points'] += match ($outcome['result']) {
                    'win' => $rules->pointsWin,
                    'draw' => $rules->pointsDraw,
                    'loss' => $rules->pointsLoss,
                };
                $stats[$id][$outcome['result'] === 'win' ? 'wins' : ($outcome['result'] === 'draw' ? 'draws' : 'losses')]++;
                $stats[$id]['score_diff'] += $outcome['diff'];
                $stats[$id]['jury'] += $outcome['jury'];
                $stats[$id]['public'] += $outcome['public'];
            }
        }

        $ranked = $this->rank(collect($stats), $matches, $rules->tieBreakers);

        DB::transaction(function () use ($ranked, $rows): void {
            foreach ($ranked->values() as $index => $stat) {
                $rows[$stat['participant_id']]->update([
                    'points' => $stat['points'],
                    'wins' => $stat['wins'],
                    'draws' => $stat['draws'],
                    'losses' => $stat['losses'],
                    'score_diff' => round($stat['score_diff'], 2),
                    'rank' => $index + 1,
                ]);
            }
        });
    }

    /**
     * @return array<int, array{result: string, diff: float, jury: float, public: float}>
     */
    private function outcomes(BattleMatch $match): array
    {
        $slots = $match->slots->whereNotNull('participant_id')->values();

        if ($slots->count() !== 2) {
            return [];
        }

        $outcomes = [];

        foreach ([[$slots[0], $slots[1]], [$slots[1], $slots[0]]] as [$self, $other]) {
            $outcomes[$self->participant_id] = [
                'result' => match ($match->winner_id) {
                    null => 'draw',
                    $self->participant_id => 'win',
                    default => 'loss',
                },
                'diff' => (float) $self->final_score - (float) $other->final_score,
                'jury' => (float) $self->jury_score,
                'public' => (float) $self->public_score,
            ];
        }

        return $outcomes;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $stats
     * @param  Collection<int, BattleMatch>  $matches
     * @param  list<TieBreaker>  $tieBreakers
     * @return Collection<int, array<string, mixed>>
     */
    private function rank(Collection $stats, Collection $matches, array $tieBreakers): Collection
    {
        return $stats
            ->sortByDesc('points')
            ->groupBy('points', preserveKeys: true)
            ->flatMap(function (Collection $tied) use ($matches, $tieBreakers) {
                if ($tied->count() === 1) {
                    return $tied;
                }

                $headToHead = $this->miniLeaguePoints($tied->keys()->all(), $matches);

                return $tied->sort(function (array $x, array $y) use ($tieBreakers, $headToHead): int {
                    foreach ($tieBreakers as $tieBreaker) {
                        $comparison = match ($tieBreaker) {
                            TieBreaker::HeadToHead => $headToHead[$y['participant_id']] <=> $headToHead[$x['participant_id']],
                            TieBreaker::ScoreDiff => $y['score_diff'] <=> $x['score_diff'],
                            TieBreaker::JuryScore => $y['jury'] <=> $x['jury'],
                            TieBreaker::PublicScore => $y['public'] <=> $x['public'],
                            TieBreaker::Seed => ($x['seed'] ?? PHP_INT_MAX) <=> ($y['seed'] ?? PHP_INT_MAX),
                        };

                        if ($comparison !== 0) {
                            return $comparison;
                        }
                    }

                    return $x['participant_id'] <=> $y['participant_id'];
                });
            });
    }

    /**
     * Wins/draws among the tied participants only (3 / 1 / 0 scale).
     *
     * @param  list<int>  $participantIds
     * @param  Collection<int, BattleMatch>  $matches
     * @return array<int, int>
     */
    private function miniLeaguePoints(array $participantIds, Collection $matches): array
    {
        $points = array_fill_keys($participantIds, 0);

        foreach ($matches as $match) {
            $ids = $match->slots->pluck('participant_id')->filter()->all();

            if (count(array_intersect($ids, $participantIds)) !== 2) {
                continue;
            }

            foreach ($ids as $id) {
                $points[$id] += match ($match->winner_id) {
                    null => 1,
                    $id => 3,
                    default => 0,
                };
            }
        }

        return $points;
    }
}
