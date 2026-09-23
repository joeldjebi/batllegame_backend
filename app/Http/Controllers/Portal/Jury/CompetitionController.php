<?php

namespace App\Http\Controllers\Portal\Jury;

use App\Enums\JudgeStatus;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\JuryScore;
use App\Services\JuryScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Jury portal: a judge only sees the competitions they are assigned to.
 */
class CompetitionController extends Controller
{
    public function index(Request $request): View
    {
        $assignments = $request->user()->judgeAssignments()
            ->where('status', JudgeStatus::Accepted)
            ->whereHas('competition')
            ->with(['competition' => fn ($q) => $q->with('organizer')->withCount([
                'matches as voting_matches_count' => fn ($q) => $q->where('status', MatchStatus::Voting),
                'criteria',
            ])])
            ->get();

        return view('portal.jury.dashboard', ['assignments' => $assignments]);
    }

    public function show(Request $request, Competition $competition): View
    {
        $judge = $this->judgeOf($request, $competition);

        $matches = $competition->matches()
            ->whereIn('status', [MatchStatus::Voting, MatchStatus::Closed])
            ->where('is_forfeit', false)
            ->with(['stage', 'phase', 'slots.participant'])
            ->orderByRaw("case status when 'vote' then 0 else 1 end")
            ->orderByDesc('stage_id')
            ->get();

        return view('portal.jury.competition', [
            'competition' => $competition->load('organizer'),
            'matches' => $matches,
            'scored' => $judge->scores()->select('match_id', 'participant_id')->distinct()->get()->groupBy('match_id')->map->pluck('participant_id'),
            'criteriaCount' => $competition->criteria()->count(),
        ]);
    }

    /**
     * $match is resolved through $competition->matches() (scoped binding).
     */
    public function match(Request $request, Competition $competition, BattleMatch $match): View
    {
        $judge = $this->judgeOf($request, $competition);

        return view('portal.jury.match', [
            'competition' => $competition,
            'match' => $match->load(['stage', 'phase', 'slots.participant']),
            'media' => $match->publishedPerformances()->groupBy('participant_id'),
            'criteria' => $competition->criteria()->get(),
            'myScores' => $judge->scores()->where('match_id', $match->id)->get()->groupBy('participant_id'),
            'canScore' => $request->user()->can('create', [JuryScore::class, $match]),
        ]);
    }

    public function score(Request $request, Competition $competition, BattleMatch $match, JuryScoringService $scoring): RedirectResponse
    {
        $judge = $this->judgeOf($request, $competition);
        $this->authorize('create', [JuryScore::class, $match]);

        $scoring->store($judge, $match, $request->all());

        return back()->with('status', 'Notes enregistrées.');
    }

    private function judgeOf(Request $request, Competition $competition): Judge
    {
        return $competition->judges()
            ->where('user_id', $request->user()->id)
            ->where('status', JudgeStatus::Accepted)
            ->firstOr(fn () => abort(404));
    }
}
