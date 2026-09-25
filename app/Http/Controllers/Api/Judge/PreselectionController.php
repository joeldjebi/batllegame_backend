<?php

namespace App\Http\Controllers\Api\Judge;

use App\Enums\JudgeStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\Preselection;
use App\Models\PreselectionScore;
use App\Models\PreselectionSubmission;
use App\Services\JuryWorkload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Judge's pre-selection in the mobile app, same rules as the /jury portal: the entries
 * to score and the ones scored (cursor-paginated), one entry with its notes. Notes are
 * final once saved (POST /competitions/{slug}/preselection/entries/{entry}/scores).
 */
class PreselectionController extends Controller
{
    public function __construct(private JuryWorkload $workload) {}

    public function index(Request $request, Competition $competition): JsonResponse
    {
        [$judge, $preselection] = $this->context($request, $competition);
        $validated = $request->validate([
            'tab' => ['nullable', 'in:a_noter,notees'],
            'q' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $scoredTab = ($validated['tab'] ?? 'a_noter') === 'notees';
        $search = trim((string) ($validated['q'] ?? ''));

        $page = $this->workload->entriesScoredBy($judge, $preselection, $scoredTab)
            ->when($search !== '', fn ($q) => $q->whereHas('participant', fn ($p) => $p->whereLike('stage_name', "%{$search}%")))
            ->with('participant.user')
            ->orderBy('id')
            ->cursorPaginate((int) ($validated['limit'] ?? 20))
            ->withQueryString();

        $totals = PreselectionScore::query()->where('judge_id', $judge->id)->whereIn('submission_id', collect($page->items())->pluck('id'))
            ->selectRaw('submission_id, SUM(score) as total')->groupBy('submission_id')->pluck('total', 'submission_id');

        return response()->json([
            'data' => collect($page->items())->map(fn (PreselectionSubmission $entry) => [
                ...$this->entry($entry),
                'my_total' => isset($totals[$entry->id]) ? (float) $totals[$entry->id] : null,
            ])->all(),
            'meta' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                ...$this->summary($judge, $preselection),
            ],
        ]);
    }

    public function show(Request $request, Competition $competition, PreselectionSubmission $entry): JsonResponse
    {
        [$judge, $preselection] = $this->context($request, $competition);
        abort_unless($this->workload->entriesFor($judge, $preselection)->whereKey($entry->id)->exists(), 404);

        $mine = PreselectionScore::query()->where('judge_id', $judge->id)->where('submission_id', $entry->id)->get();

        return response()->json([
            'data' => [
                ...$this->entry($entry->load('participant.user')),
                'my_scores' => $mine->map(fn (PreselectionScore $score) => ['criterion_id' => $score->criterion_id, 'score' => (float) $score->score, 'comment' => $score->comment])->values(),
                'can_score' => $preselection->acceptsScores() && $mine->isEmpty(),
                'next_entry_id' => $this->workload->nextToScore($judge, $preselection, $entry->id),
            ],
            'meta' => $this->summary($judge, $preselection),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(PreselectionSubmission $entry): array
    {
        return [
            'id' => $entry->id,
            'stage_name' => $entry->participant->stage_name,
            'avatar_url' => $entry->participant->user?->avatarUrl(),
            'media' => ['type' => $entry->media_type, 'url' => $entry->mediaUrl(), 'poster_url' => $entry->posterUrl(), 'width' => $entry->width, 'height' => $entry->height, 'duration_seconds' => $entry->duration_seconds],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Judge $judge, Preselection $preselection): array
    {
        $total = $this->workload->entriesFor($judge, $preselection)->count();
        $scored = $this->workload->entriesScoredBy($judge, $preselection, true)->count();

        return [
            'state' => $preselection->state(),
            'accepts_scores' => $preselection->acceptsScores(),
            'deliberation_ends_at' => $preselection->deliberationEndsAt(),
            'splits_judging' => $preselection->splitsJudging(),
            'jury_weight' => $preselection->effectiveWeights()['jury'],
            'total' => $total,
            'scored' => $scored,
            'next_entry_id' => $this->workload->nextToScore($judge, $preselection),
            'criteria' => $preselection->competition->criteria()->get(['id', 'name', 'max_points'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'max_points' => $c->max_points]),
        ];
    }

    /**
     * @return array{0: Judge, 1: Preselection}
     */
    private function context(Request $request, Competition $competition): array
    {
        $judge = $competition->judges()->where('user_id', $request->user()->id)->where('status', JudgeStatus::Accepted)->firstOr(fn () => abort(404));
        $preselection = $competition->preselection ?? abort(404);
        $this->workload->sync($preselection);

        return [$judge, $preselection];
    }
}
