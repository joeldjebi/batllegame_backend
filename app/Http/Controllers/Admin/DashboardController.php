<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompetitionStatus;
use App\Enums\MatchStatus;
use App\Enums\OrganizerStatus;
use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Judge;
use App\Models\Organizer;
use App\Models\Participant;
use App\Models\PublicVote;
use App\Models\User;
use Illuminate\View\View;

/**
 * Platform-wide overview for the super-admin.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $countBy = fn (string $model) => $model::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.dashboard', [
            'organizerCounts' => $countBy(Organizer::class),
            'competitionCounts' => $countBy(Competition::class),
            'stats' => [
                'users' => User::query()->count(),
                'verified_users' => User::query()->whereNotNull('phone_verified_at')->count(),
                'backoffice_users' => User::query()->whereNotNull('email')->count(),
                'participants' => Participant::query()->count(),
                'judges' => Judge::query()->count(),
                'votes' => PublicVote::query()->count(),
                'votes_24h' => PublicVote::query()->where('created_at', '>=', now()->subDay())->count(),
                'voting_matches' => BattleMatch::query()->where('status', MatchStatus::Voting)->count(),
            ],
            'pendingOrganizers' => Organizer::query()
                ->where('status', OrganizerStatus::Pending)
                ->with(['members' => fn ($q) => $q->where('role', 'owner')->with('user:id,name,email')])
                ->latest()->limit(5)->get(),
            'liveCompetitions' => Competition::query()
                ->where('status', CompetitionStatus::InProgress)
                ->with('organizer')
                ->withCount([
                    'participants',
                    'matches',
                    'matches as decided_matches_count' => fn ($q) => $q->whereIn('status', [MatchStatus::Closed, MatchStatus::Cancelled]),
                    'publicVotes',
                ])
                ->latest('updated_at')->limit(6)->get(),
            'recentUsers' => User::query()->with('country')->latest()->limit(6)->get(),
        ]);
    }
}
