<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\VoteMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Models\BattleMatch;
use App\Models\MatchParticipant;
use App\Models\PublicVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * « Battles » tab of the mobile app (no live video: the matches whose public vote is open now),
 * soonest to close first — duels (two artists) and groups — with each artist's
 * performance and the viewer's vote (groups: one vote for the whole phase).
 * Also the battles coming next (submissions running, vote planned), so the tab
 * says when the next vote opens instead of staying empty.
 */
class LiveController extends Controller
{
    private const int LIMIT = 30;

    private const int UPCOMING_LIMIT = 10;

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
            'upcoming' => $this->upcoming()->map(fn (array $row) => [
                'id' => $row['match']->id,
                'is_group' => $row['match']->isGroupMatch(),
                'title' => $row['match']->title(),
                'stage' => $row['match']->stage?->name,
                'competition' => ['id' => $row['match']->competition->id, 'slug' => $row['match']->competition->slug, 'name' => $row['match']->competition->name],
                'voting_opens_at' => $row['opens_at']->toIso8601String(),
                // Online: artists are still sending their performance.
                'submissions_open' => $row['match']->status === MatchStatus::Submissions,
                'is_mine' => $viewer !== null && $row['match']->slots->contains(fn (MatchParticipant $slot) => $slot->participant?->user_id === $viewer->id),
                'artists' => $row['match']->slots->sortBy('slot')->filter(fn (MatchParticipant $slot) => $slot->participant !== null)->values()->map(fn (MatchParticipant $slot) => [
                    'participant_id' => $slot->participant_id,
                    'stage_name' => $slot->participant->stage_name,
                    'avatar_url' => $slot->participant->user?->avatarUrl(),
                ]),
            ])->values(),
        ]);
    }

    /**
     * Matches whose public vote opens later (known date), soonest first. Jury-only
     * phases are left out: the public never votes there.
     *
     * @return Collection<int, array{match: BattleMatch, opens_at: Carbon}>
     */
    private function upcoming(): Collection
    {
        return BattleMatch::query()
            ->whereIn('status', [MatchStatus::Submissions, MatchStatus::Scheduled])
            ->whereDoesntHave('slots', fn ($q) => $q->whereNull('participant_id'))
            ->whereHas('competition', fn ($q) => $q->where('status', '!=', CompetitionStatus::Draft))
            // The vote never opens before the end of the submissions: the later of both dates.
            ->whereHas('stage', fn ($q) => $q->where(fn ($q) => $q->where('voting_opens_at', '>', now())->orWhere('submission_deadline', '>', now())))
            ->with(['competition', 'stage', 'group', 'phase', 'slots.participant.user'])
            ->limit(self::UPCOMING_LIMIT * 4)
            ->get()
            ->reject(fn (BattleMatch $match) => $match->phase->rules->voteMode === VoteMode::Jury)
            ->map(fn (BattleMatch $match) => ['match' => $match, 'opens_at' => max(array_filter([$match->stage->voting_opens_at, $match->stage->submission_deadline]))])
            ->sortBy(fn (array $row) => $row['opens_at']->getTimestamp())
            ->take(self::UPCOMING_LIMIT)
            ->values();
    }
}
