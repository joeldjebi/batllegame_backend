<?php

namespace App\Http\Controllers\Api\Judge;

use App\Enums\JudgeStatus;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Http\Resources\StageResource;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Judge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Judge area of the mobile app: a judge only ever sees the competitions they
 * are assigned to, and the matches they have to score.
 */
class CompetitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assignments = $request->user()->judgeAssignments()
            ->where('status', JudgeStatus::Accepted)
            ->whereHas('competition')
            ->with(['competition' => fn ($q) => $q->with('organizer')->withCount([
                'matches as matches_to_score_count' => fn ($q) => $q->where('status', MatchStatus::Voting),
            ])])
            ->get();

        return response()->json([
            'data' => $assignments->map(fn (Judge $judge) => [
                'id' => $judge->competition->id,
                'slug' => $judge->competition->slug,
                'name' => $judge->competition->name,
                'status' => $judge->competition->status,
                'discipline' => $judge->competition->discipline,
                'mode' => $judge->competition->mode,
                'organizer' => $judge->competition->organizer->name,
                'matches_to_score' => $judge->competition->matches_to_score_count,
            ]),
        ]);
    }

    public function show(Request $request, Competition $competition): JsonResponse
    {
        $judge = $this->judgeOf($request, $competition);

        $matches = $competition->matches()
            ->whereIn('status', [MatchStatus::Voting, MatchStatus::Closed])
            ->with(['phase', 'stage', 'slots.participant', 'competition'])
            ->orderByRaw("case status when 'vote' then 0 else 1 end")
            ->orderBy('stage_id')
            ->get();

        $scored = $judge->scores()->select('match_id', 'participant_id')->distinct()->get()->groupBy('match_id');

        return response()->json([
            'competition' => ['id' => $competition->id, 'slug' => $competition->slug, 'name' => $competition->name, 'status' => $competition->status],
            'criteria' => $competition->criteria()->get(['id', 'name', 'max_points', 'weight']),
            'stages' => StageResource::collection($competition->stages()->with('phase.competition')->orderBy('phase_id')->orderBy('number')->get()),
            'matches' => $matches->map(fn (BattleMatch $match) => [
                ...(new MatchResource($match))->resolve($request),
                'scored_participants' => $scored->get($match->id, collect())->pluck('participant_id'),
                'to_score' => $match->acceptsJuryScores() && $match->phase->rules->usesJury(),
                'deliberation_ends_at' => $match->deliberation_ends_at,
            ]),
        ]);
    }

    /**
     * $match is resolved through $competition->matches() (scoped binding).
     */
    public function match(Request $request, Competition $competition, BattleMatch $match): JsonResponse
    {
        $judge = $this->judgeOf($request, $competition);

        return response()->json([
            'match' => new MatchResource($match->load(['phase', 'stage', 'slots.participant', 'competition'])),
            'criteria' => $competition->criteria()->get(['id', 'name', 'max_points', 'weight']),
            'my_scores' => $judge->scores()->where('match_id', $match->id)->get(['participant_id', 'criterion_id', 'score', 'comment']),
        ]);
    }

    private function judgeOf(Request $request, Competition $competition): Judge
    {
        // Not assigned: the competition does not exist for this judge.
        return $competition->judges()
            ->where('user_id', $request->user()->id)
            ->where('status', JudgeStatus::Accepted)
            ->firstOr(fn () => abort(404));
    }
}
