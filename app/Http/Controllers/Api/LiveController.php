<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompetitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\BattleMatch;
use App\Models\MatchParticipant;
use App\Models\PublicVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « Battles » tab of the mobile app (no live video: the matches whose public vote is open now),
 * soonest to close first — duels (two artists) and groups — with each artist's
 * performance and the viewer's vote (groups: one vote for the whole phase).
 */
class LiveController extends Controller
{
    private const int LIMIT = 30;

    public function __invoke(Request $request): JsonResponse
    {
        $viewer = $request->user('sanctum');

        $matches = BattleMatch::query()
            ->votingNow()
            ->whereHas('competition', fn ($q) => $q->where('status', '!=', CompetitionStatus::Draft))
            ->with(['competition', 'stage', 'group', 'phase', 'slots.participant.user'])
            ->orderByRaw('voting_closes_at is null')
            ->orderBy('voting_closes_at')
            ->limit(self::LIMIT)
            ->get()
            ->filter(fn (BattleMatch $match) => $match->isVotingOpen())
            ->values();

        // The viewer's votes: in these matches, and anywhere in the group phases shown.
        $votes = collect();
        if ($viewer && $matches->isNotEmpty()) {
            $phaseIds = $matches->filter->isGroupMatch()->pluck('phase_id')->unique();
            $votes = PublicVote::query()->where('user_id', $viewer->id)
                ->where(fn ($q) => $q->whereIn('match_id', $matches->pluck('id'))
                    ->orWhereIn('match_id', BattleMatch::query()->whereIn('phase_id', $phaseIds)->select('id')))
                ->with('match:id,phase_id')
                ->get();
        }

        return response()->json([
            'data' => $matches->map(function (BattleMatch $match) use ($votes, $viewer) {
                $media = $match->publishedPerformances()->groupBy('participant_id');
                $mine = $votes->firstWhere('match_id', $match->id);
                $phaseVote = $match->isGroupMatch() ? $votes->first(fn (PublicVote $v) => $v->match?->phase_id === $match->phase_id) : null;
                $artists = $match->slots->sortBy('slot')->filter(fn (MatchParticipant $slot) => $slot->participant !== null && ! $slot->is_forfeit)->values();

                return [
                    'id' => $match->id,
                    'is_group' => $match->isGroupMatch(),
                    'title' => $match->title(),
                    'stage' => $match->stage?->name,
                    'competition' => ['id' => $match->competition->id, 'slug' => $match->competition->slug, 'name' => $match->competition->name],
                    'voting_closes_at' => $match->voting_closes_at?->toIso8601String(),
                    'vote_code_required' => $match->vote_code !== null,
                    'share_url' => route('fan.competitions.show', $match->competition).'#match-'.$match->id,
                    'my_vote' => $mine?->participant_id,
                    // Groups: already voted elsewhere in this phase.
                    'voted_in_phase' => $phaseVote !== null && $mine === null,
                    'is_mine' => $viewer !== null && $artists->contains(fn (MatchParticipant $slot) => $slot->participant->user_id === $viewer->id),
                    'artists' => $artists->map(fn (MatchParticipant $slot) => [
                        'participant_id' => $slot->participant_id,
                        'stage_name' => $slot->participant->stage_name,
                        'avatar_url' => $slot->participant->user?->avatarUrl(),
                        'media' => ($first = $media->get($slot->participant_id)?->first()) ? (new MediaResource($first))->resolve() : null,
                    ]),
                ];
            }),
        ]);
    }
}
