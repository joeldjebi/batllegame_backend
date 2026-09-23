<?php

namespace App\Http\Controllers;

use App\Enums\CompetitionStatus;
use App\Enums\OrganizerStatus;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\PublicVote;
use Illuminate\View\View;

/**
 * Public landing page: follow the competitions and join as fan or artist.
 */
class LandingController extends Controller
{
    public function __invoke(): View
    {
        $public = fn () => Competition::query()
            ->whereHas('organizer', fn ($q) => $q->where('status', '!=', OrganizerStatus::Suspended))
            ->with('organizer')
            ->withCount('participants');

        return view('landing', [
            'live' => BattleMatch::query()
                ->votingNow()
                ->whereHas('competition', fn ($q) => $q->where('status', CompetitionStatus::InProgress))
                ->with(['competition', 'stage', 'slots.participant'])
                ->withCount('publicVotes')
                ->latest('voting_opens_at')
                ->limit(3)
                ->get(),
            'inProgress' => $public()->where('status', CompetitionStatus::InProgress)
                ->withCount(['matches as voting_matches_count' => fn ($q) => $q->votingNow()])
                ->latest('updated_at')->limit(6)->get(),
            'open' => $public()->where('status', CompetitionStatus::Registration)->latest()->limit(6)->get(),
            // Stage names for the scrolling band (latest artists first).
            'artistNames' => Participant::query()->latest()->limit(40)->pluck('stage_name')->unique()->take(16)->values(),
            'stats' => [
                'competitions' => Competition::query()->where('status', '!=', CompetitionStatus::Draft)->count(),
                'artists' => Participant::query()->distinct('user_id')->count('user_id'),
                'votes' => PublicVote::query()->count(),
                'organizers' => Organizer::query()->where('status', OrganizerStatus::Verified)->count(),
                'live' => BattleMatch::query()->votingNow()->count(),
            ],
        ]);
    }
}
