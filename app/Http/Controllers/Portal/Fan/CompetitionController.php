<?php

namespace App\Http\Controllers\Portal\Fan;

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\PublicVote;
use Illuminate\Http\Request;
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
                ->with('organizer')
                ->withCount([
                    'participants',
                    'matches as voting_matches_count' => fn ($q) => $q->where('status', MatchStatus::Voting),
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
            ->with(['stage', 'phase', 'slots.participant'])
            ->latest('updated_at')
            ->get();

        return view('portal.fan.competition', [
            'competition' => $competition->load('organizer'),
            'voting' => $matches->where('status', MatchStatus::Voting)->values(),
            'results' => $matches->where('status', MatchStatus::Closed)->take(12)->values(),
            'myVotes' => $user ? PublicVote::query()->where('user_id', $user->id)->whereIn('match_id', $matches->modelKeys())->pluck('participant_id', 'match_id') : collect(),
            'user' => $user,
        ]);
    }
}
