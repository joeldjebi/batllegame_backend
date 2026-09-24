<?php

namespace App\Services;

use App\Enums\JudgeStatus;
use App\Enums\PerformanceStatus;
use App\Models\Judge;
use App\Models\Preselection;
use App\Models\PreselectionScore;
use App\Models\PreselectionSubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which pre-selection entries each judge scores. By default every accepted judge scores
 * every approved entry. With judges_per_entry, each entry goes to that many judges,
 * balanced by load; assignments are kept (and never removed once scored) so a judge's
 * list only grows with new entries or a new colleague.
 */
class JuryWorkload
{
    /**
     * Assign the approved entries still missing judges. Idempotent, cheap when nothing changed.
     */
    public function sync(Preselection $preselection): void
    {
        if (! $preselection->splitsJudging()) {
            return;
        }

        DB::transaction(function () use ($preselection): void {
            $judgeIds = $preselection->competition->judges()->where('status', JudgeStatus::Accepted)->orderBy('id')->pluck('id');
            $entryIds = $preselection->entries()->where('status', PerformanceStatus::Approved)->pluck('id');
            $scored = PreselectionScore::query()->whereIn('submission_id', $entryIds)->select('submission_id', 'judge_id')->distinct()->get()
                ->map(fn ($row) => $row->submission_id.'-'.$row->judge_id)->flip();

            // Judges who left: their unscored assignments go back to the pool.
            $stale = DB::table('preselection_assignments')->whereIn('submission_id', $entryIds)->whereNotIn('judge_id', $judgeIds)->get();
            $toDelete = $stale->reject(fn ($row) => $scored->has($row->submission_id.'-'.$row->judge_id))->pluck('id');
            DB::table('preselection_assignments')->whereIn('id', $toDelete)->delete();

            $per = min($preselection->judges_per_entry, $judgeIds->count());
            if ($per === 0) {
                return;
            }

            $assigned = DB::table('preselection_assignments')->whereIn('submission_id', $entryIds)->whereIn('judge_id', $judgeIds)->get(['submission_id', 'judge_id'])->groupBy('submission_id');
            $load = $judgeIds->mapWithKeys(fn ($id) => [$id => 0])->all();
            foreach ($assigned->flatten(1) as $row) {
                $load[$row->judge_id]++;
            }

            $rows = [];
            foreach ($entryIds as $entryId) {
                $current = $assigned->get($entryId, collect())->pluck('judge_id')->all();
                for ($missing = $per - count($current); $missing > 0; $missing--) {
                    // Least loaded judge not yet on this entry (lowest id on ties: stable).
                    $candidates = array_diff_key($load, array_flip($current));
                    asort($candidates);
                    $judgeId = array_key_first($candidates);
                    $current[] = $judgeId;
                    $load[$judgeId]++;
                    $rows[] = ['submission_id' => $entryId, 'judge_id' => $judgeId, 'created_at' => now(), 'updated_at' => now()];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('preselection_assignments')->insertOrIgnore($chunk);
            }
        });
    }

    /**
     * Approved entries this judge scores.
     *
     * @return Builder<PreselectionSubmission>
     */
    public function entriesFor(Judge $judge, Preselection $preselection): Builder
    {
        return $preselection->entries()->getQuery()
            ->where('status', PerformanceStatus::Approved)
            ->when($preselection->splitsJudging(), fn (Builder $q) => $q->whereIn('id', DB::table('preselection_assignments')->where('judge_id', $judge->id)->select('submission_id')));
    }

    public function isAssigned(Judge $judge, PreselectionSubmission $entry): bool
    {
        return ! $entry->preselection->splitsJudging()
            || DB::table('preselection_assignments')->where('judge_id', $judge->id)->where('submission_id', $entry->id)->exists();
    }

    public function hasScored(Judge $judge, PreselectionSubmission $entry): bool
    {
        return PreselectionScore::query()->where('judge_id', $judge->id)->where('submission_id', $entry->id)->exists();
    }

    /**
     * Progress of each accepted judge: [judge, scored, total].
     *
     * @return list<array{judge: Judge, scored: int, total: int}>
     */
    public function progress(Preselection $preselection): array
    {
        $this->sync($preselection);

        return $preselection->competition->judges()->where('status', JudgeStatus::Accepted)->with('user')->get()
            ->map(fn (Judge $judge) => [
                'judge' => $judge,
                'scored' => PreselectionScore::query()->where('judge_id', $judge->id)->whereIn('submission_id', $this->entriesFor($judge, $preselection)->select('id'))->distinct()->count('submission_id'),
                'total' => $this->entriesFor($judge, $preselection)->count(),
            ])->all();
    }
}
