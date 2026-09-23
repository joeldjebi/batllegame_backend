<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompetitionStatus;
use App\Enums\Discipline;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only access to every competition of the platform.
 */
class CompetitionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(CompetitionStatus::class)],
            'discipline' => ['nullable', Rule::enum(Discipline::class)],
        ]);

        return view('admin.competitions.index', [
            'competitions' => Competition::query()
                ->with('organizer')
                ->withCount(['participants', 'judges', 'matches', 'publicVotes'])
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->when($request->query('discipline'), fn ($q, $discipline) => $q->where('discipline', $discipline))
                ->when($request->query('q'), fn ($q, $search) => $q->where(fn ($q) => $q
                    ->whereLike('name', "%{$search}%")
                    ->orWhereHas('organizer', fn ($q) => $q->whereLike('name', "%{$search}%"))))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'counts' => Competition::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    /**
     * $competition is resolved through $organizer->competitions() (scoped binding).
     */
    public function show(Organizer $organizer, Competition $competition): View
    {
        $competition->load([
            'creator',
            'phases.groups.standings.participant',
            'phases.matches' => fn ($q) => $q->orderBy('group_id')->orderBy('bracket')->orderBy('round')->orderBy('bracket_position'),
            'phases.matches.slots.participant',
            'phases.matches.group',
            'participants.user',
            'judges.user',
            'criteria',
        ]);

        return view('admin.competitions.show', [
            'organizer' => $organizer,
            'competition' => $competition,
            'stats' => [
                'votes' => $competition->publicVotes()->count(),
                'voters' => $competition->publicVotes()->distinct('user_id')->count('user_id'),
                'votes_24h' => $competition->publicVotes()->where('created_at', '>=', now()->subDay())->count(),
                'jury_scores' => $competition->juryScores()->count(),
                'voting_matches' => $competition->matches()->where('status', MatchStatus::Voting)->count(),
            ],
        ]);
    }
}
