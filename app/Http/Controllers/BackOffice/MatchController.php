<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\MatchStatus;
use App\Exceptions\CompetitionFlowException;
use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Organizer;
use App\Services\Competition\MatchCloser;
use App\Services\Competition\StageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * $match is resolved through $competition->matches() (scoped binding).
 */
class MatchController extends Controller
{
    public function update(Request $request, Organizer $organizer, Competition $competition, BattleMatch $match): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        if ($match->isClosed()) {
            throw CompetitionFlowException::matchNotPlayable();
        }

        $validated = $request->validate([
            'scheduled_at' => ['nullable', 'date'],
            'submission_deadline' => ['nullable', 'date'],
            'voting_opens_at' => ['nullable', 'date'],
            'voting_closes_at' => ['nullable', 'date', 'after:voting_opens_at'],
        ]);
        $match->fill($validated);

        if (array_key_exists('voting_closes_at', $validated)) {
            $match->deliberation_ends_at = $match->stage?->deliberationEndFor($match->voting_closes_at);
        }

        $match->save();

        return back()->with('status', 'Match programmé.');
    }

    /**
     * On-site: open the vote of a match right after the battle, optionally for a fixed duration.
     */
    public function openVoting(Request $request, Organizer $organizer, Competition $competition, BattleMatch $match, StageService $stages): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $validated = $request->validate([
            'duration' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'deliberation' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ]);

        $playable = in_array($match->status, [MatchStatus::Scheduled, MatchStatus::Submissions], true)
            && $match->slots()->whereNotNull('participant_id')->count() === 2;

        if (! $playable) {
            throw CompetitionFlowException::matchNotPlayable();
        }

        $match = $stages->openMatchVoting(
            $match,
            isset($validated['duration']) ? now()->addMinutes((int) $validated['duration']) : null,
            isset($validated['deliberation']) ? (int) $validated['deliberation'] : null,
        );

        return back()->with('status', $match->vote_code ? "Vote ouvert : code de salle {$match->vote_code}." : 'Vote ouvert.');
    }

    public function close(Request $request, Organizer $organizer, Competition $competition, BattleMatch $match, MatchCloser $closer): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $validated = $request->validate(['winner_id' => ['nullable', 'integer']]);

        $closer->close($match, isset($validated['winner_id']) ? (int) $validated['winner_id'] : null);

        return back()->with('status', 'Match clôturé.');
    }
}
