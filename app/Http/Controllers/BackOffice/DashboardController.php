<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Overview of the organizers the current user belongs to.
     */
    public function __invoke(Request $request): View
    {
        $organizers = $request->user()->organizers()
            ->withCount([
                'competitions',
                'competitions as live_competitions_count' => fn ($q) => $q->where('status', CompetitionStatus::InProgress),
            ])
            ->orderBy('name')
            ->get();

        $competitions = Competition::query()->whereIn('organizer_id', $organizers->modelKeys());

        return view('dashboard', [
            'organizers' => $organizers,
            'stats' => [
                'competitions' => (clone $competitions)->count(),
                'live' => (clone $competitions)->where('status', CompetitionStatus::InProgress)->count(),
                'registrations' => (clone $competitions)->where('status', CompetitionStatus::Registration)->count(),
                'participants' => Participant::query()->whereIn('competition_id', (clone $competitions)->select('id'))->count(),
                'voting' => BattleMatch::query()->whereIn('competition_id', (clone $competitions)->select('id'))->where('status', MatchStatus::Voting)->count(),
            ],
            'recentCompetitions' => (clone $competitions)->with('organizer')->withCount('participants')->latest()->limit(6)->get(),
        ]);
    }
}
