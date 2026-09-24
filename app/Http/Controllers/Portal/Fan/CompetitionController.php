<?php

namespace App\Http\Controllers\Portal\Fan;

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\PerformanceStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Preselection;
use App\Models\PreselectionSubmission;
use App\Models\PublicVote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public portal: browse competitions, watch the battles and vote.
 */
class CompetitionController extends Controller
{
    public function index(): View
    {
        return view('portal.fan.index', [
            'competitions' => Competition::query()
                ->whereIn('status', [CompetitionStatus::InProgress, CompetitionStatus::Registration, CompetitionStatus::Finished])
                ->with(['organizer', 'city', 'commune'])
                ->withCount([
                    'participants',
                    'matches as voting_matches_count' => fn ($q) => $q->votingNow(),
                ])
                ->orderByRaw("case status when 'en_cours' then 0 when 'inscriptions' then 1 else 2 end")
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, Competition $competition): View
    {
        abort_unless($competition->status->isPublic(), 404);

        $user = auth('member')->user();
        $matches = $competition->matches()
            ->whereIn('status', [MatchStatus::Voting, MatchStatus::Closed])
            ->where('is_forfeit', false)
            ->with(['stage', 'phase', 'group', 'slots.participant'])
            ->latest('updated_at')
            ->get();

        $preselection = $competition->preselection;
        $myVotes = $user ? PublicVote::query()->where('user_id', $user->id)->whereIn('match_id', $matches->modelKeys())->get(['match_id', 'participant_id']) : collect();
        $closed = $matches->where('status', MatchStatus::Closed);
        $groupPhases = $matches->filter->isGroupMatch()->pluck('phase_id', 'id');

        return view('portal.fan.competition', [
            'preselection' => $preselection,
            'entries' => $preselection ? $this->entries($preselection, $request) : collect(),
            'likes' => $preselection ? PreselectionController::likesState($preselection, $user) : null,
            'competition' => $competition->load('organizer'),
            'voting' => $matches->filter->isVotingOpen()->values(),
            'results' => $closed->reject->isGroupMatch()->take(12)->values(),
            // Groups: the ranking once the organizer published the phase results.
            'groupResults' => $closed->filter(fn ($match) => $match->isGroupMatch() && $match->resultsArePublic())->sortBy('bracket_position')->groupBy('phase_id'),
            'myVotes' => $myVotes->pluck('participant_id', 'match_id'),
            // One vote per group phase: the artist voted for, by phase.
            'phaseVotes' => $myVotes->filter(fn ($vote) => $groupPhases->has($vote->match_id))->mapWithKeys(fn ($vote) => [$groupPhases[$vote->match_id] => $vote->participant_id]),
            'user' => $user,
        ]);
    }

    /**
     * Published entries: by rank once published, otherwise shuffled but stable for a viewer
     * (fair to the artists, no card jumping when the page refreshes live).
     *
     * @return Collection<int, PreselectionSubmission>
     */
    private function entries(Preselection $preselection, Request $request): Collection
    {
        $entries = $preselection->entries()->where('status', PerformanceStatus::Approved)->with('participant.user')->get();
        $seed = (string) ($request->user()?->id ?? $request->session()->getId());

        return $preselection->published_at
            ? $entries->sortBy('rank')->values()
            : $entries->sortBy(fn (PreselectionSubmission $entry) => crc32($seed.'-'.$entry->id))->values();
    }
}
