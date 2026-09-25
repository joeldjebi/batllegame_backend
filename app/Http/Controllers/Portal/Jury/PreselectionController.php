<?php

namespace App\Http\Controllers\Portal\Jury;

use App\Enums\JudgeStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\Preselection;
use App\Models\PreselectionScore;
use App\Models\PreselectionSubmission;
use App\Services\JuryWorkload;
use App\Services\PreselectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Judges score the pre-selection entries of their competitions: a compact paginated list
 * (to score / scored) and one entry at a time. Notes are final once saved.
 */
class PreselectionController extends Controller
{
    private const int PER_PAGE = 20;

    public function __construct(private JuryWorkload $workload) {}

    public function index(Request $request, Competition $competition): View
    {
        [$judge, $preselection] = $this->context($request, $competition);
        $tab = $request->query('onglet') === 'notees' ? 'notees' : 'a_noter';
        $search = trim((string) $request->query('q'));

        $entries = $this->entries($judge, $preselection, $tab === 'notees')
            ->when($search !== '', fn (Builder $q) => $q->whereHas('participant', fn (Builder $p) => $p->whereLike('stage_name', "%{$search}%")))
            ->with('participant.user')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $total = $this->workload->entriesFor($judge, $preselection)->count();
        $scored = $this->entries($judge, $preselection, true)->count();

        return view('portal.jury.preselection', [
            'competition' => $competition,
            'preselection' => $preselection,
            'judge' => $judge,
            'entries' => $entries,
            'tab' => $tab,
            'search' => $search,
            'total' => $total,
            'scored' => $scored,
            'next' => $this->workload->nextToScore($judge, $preselection),
            'myScores' => PreselectionScore::query()->where('judge_id', $judge->id)->whereIn('submission_id', $entries->getCollection()->modelKeys())->get()->groupBy('submission_id'),
        ]);
    }

    /**
     * One entry: the player and the scoring form (or the notes already given, read-only).
     */
    public function show(Request $request, Competition $competition, PreselectionSubmission $entry): View
    {
        [$judge, $preselection] = $this->context($request, $competition);
        abort_unless($this->workload->entriesFor($judge, $preselection)->whereKey($entry->id)->exists(), 404);

        $mine = PreselectionScore::query()->where('judge_id', $judge->id)->where('submission_id', $entry->id)->get()->keyBy('criterion_id');

        return view('portal.jury.preselection-entry', [
            'competition' => $competition,
            'preselection' => $preselection,
            'entry' => $entry->load('participant.user'),
            'criteria' => $competition->criteria()->get(),
            'mine' => $mine,
            'canScore' => $preselection->acceptsScores() && $mine->isEmpty(),
            'next' => $this->workload->nextToScore($judge, $preselection, $entry->id),
            'total' => $this->workload->entriesFor($judge, $preselection)->count(),
            'scored' => $this->entries($judge, $preselection, true)->count(),
        ]);
    }

    public function score(Request $request, Competition $competition, PreselectionSubmission $entry, PreselectionService $preselections): RedirectResponse
    {
        [$judge, $preselection] = $this->context($request, $competition);
        $this->authorize('score', $entry);

        $preselections->score($judge, $entry, $request->all());

        // Straight to the next entry to score.
        $next = $this->workload->nextToScore($judge, $preselection, $entry->id);
        $message = "Notes enregistrées pour {$entry->participant->stage_name}.";

        return $next
            ? redirect()->route('jury.competitions.preselection.entries.show', [$competition, $next])->with('status', $message)
            : redirect()->route('jury.competitions.preselection', [$competition, 'onglet' => 'notees'])->with('status', $message.' Toutes tes prestations sont notées.');
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

    /**
     * @return Builder<PreselectionSubmission>
     */
    private function entries(Judge $judge, Preselection $preselection, bool $scored): Builder
    {
        return $this->workload->entriesScoredBy($judge, $preselection, $scored);
    }
}
