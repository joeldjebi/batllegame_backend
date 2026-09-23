<?php

namespace App\Http\Controllers\Portal\Jury;

use App\Enums\JudgeStatus;
use App\Enums\PerformanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\PreselectionScore;
use App\Models\PreselectionSubmission;
use App\Services\PreselectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Judges score the pre-selection entries of their competitions.
 */
class PreselectionController extends Controller
{
    public function index(Request $request, Competition $competition): View
    {
        $judge = $this->judgeOf($request, $competition);
        $preselection = $competition->preselection ?? abort(404);

        return view('portal.jury.preselection', [
            'competition' => $competition,
            'preselection' => $preselection,
            'entries' => $preselection->entries()->where('status', PerformanceStatus::Approved)->with('participant')->orderBy('id')->get(),
            'criteria' => $competition->criteria()->get(),
            'myScores' => PreselectionScore::query()->where('judge_id', $judge->id)->whereIn('submission_id', $preselection->entries()->select('id'))->get()->groupBy('submission_id'),
        ]);
    }

    /**
     * $entry is resolved through $competition->entries() (scoped binding).
     */
    public function score(Request $request, Competition $competition, PreselectionSubmission $entry, PreselectionService $preselections): RedirectResponse
    {
        $judge = $this->judgeOf($request, $competition);
        $this->authorize('score', $entry);

        $preselections->score($judge, $entry, $request->all());

        return back()->with('status', "Notes enregistrées pour {$entry->participant->stage_name}.");
    }

    private function judgeOf(Request $request, Competition $competition): Judge
    {
        return $competition->judges()->where('user_id', $request->user()->id)->where('status', JudgeStatus::Accepted)->firstOr(fn () => abort(404));
    }
}
