<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompetitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompetitionResource;
use App\Http\Resources\MatchResource;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\MatchParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Public read access for the mobile app. Drafts are never exposed.
 */
class CompetitionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_filter(CompetitionStatus::values(), fn ($s) => $s !== CompetitionStatus::Draft->value))],
            'discipline' => ['nullable', 'string'],
        ]);

        $competitions = Competition::query()
            ->with('organizer')
            ->where('status', '!=', CompetitionStatus::Draft)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('discipline'), fn ($q, $discipline) => $q->where('discipline', $discipline))
            ->latest()
            ->paginate(20);

        return CompetitionResource::collection($competitions);
    }

    public function show(Competition $competition): CompetitionResource
    {
        abort_unless($competition->status->isPublic(), 404);

        return new CompetitionResource($competition->load(['organizer', 'phases', 'criteria']));
    }

    /**
     * Matches of a competition for the app's groups and bracket views, light (no media):
     * one phase at a time (`phase` id, default the first), ordered like the back-office.
     */
    public function matches(Request $request, Competition $competition): JsonResponse
    {
        abort_unless($competition->status->isPublic(), 404);
        $validated = $request->validate(['phase' => ['nullable', 'integer']]);

        $phase = $competition->phases()
            ->when($validated['phase'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->firstOr(fn () => abort(404));

        $matches = $phase->matches()
            ->with(['group', 'stage', 'slots.participant.user', 'competition', 'phase'])
            ->orderBy('group_id')->orderBy('bracket')->orderBy('round')->orderBy('bracket_position')
            ->get();

        return response()->json([
            'phase' => ['id' => $phase->id, 'position' => $phase->position, 'type' => $phase->type, 'status' => $phase->status],
            'data' => $matches->map(function (BattleMatch $match) {
                $public = $match->resultsArePublic();

                return [
                    'id' => $match->id,
                    'is_group' => $match->isGroupMatch(),
                    'title' => $match->title(),
                    'group' => $match->group?->name,
                    'bracket' => $match->bracket,
                    'round' => $match->round,
                    'bracket_position' => $match->bracket_position,
                    'status' => $match->status,
                    'stage' => $match->stage?->name,
                    'voting_open' => $match->isVotingOpen(),
                    'voting_closes_at' => $match->voting_closes_at,
                    'winner_id' => $public ? $match->winner_id : null,
                    'slots' => $match->slots->sortBy('slot')->values()->map(fn (MatchParticipant $slot) => [
                        'participant_id' => $slot->participant_id,
                        'stage_name' => $slot->participant?->stage_name,
                        'avatar_url' => $slot->participant?->user?->avatarUrl(),
                        'final_score' => $public && $slot->final_score !== null ? (float) $slot->final_score : null,
                        'rank' => $public ? $slot->rank : null,
                        'is_forfeit' => $slot->is_forfeit,
                    ]),
                ];
            }),
        ]);
    }

    /**
     * $match is resolved through $competition->matches() (scoped binding).
     */
    public function showMatch(Competition $competition, BattleMatch $match): MatchResource
    {
        abort_unless($competition->status->isPublic(), 404);

        return new MatchResource($match->load(['phase', 'slots.participant.user', 'competition', 'group']));
    }
}
