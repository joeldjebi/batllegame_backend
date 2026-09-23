<?php

namespace App\Http\Controllers\BackOffice;

use App\Enums\MatchStatus;
use App\Exceptions\CompetitionFlowException;
use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\Organizer;
use App\Services\Competition\MatchCloser;
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

        $match->update($request->validate([
            'scheduled_at' => ['nullable', 'date'],
            'submission_deadline' => ['nullable', 'date'],
            'voting_opens_at' => ['nullable', 'date'],
            'voting_closes_at' => ['nullable', 'date', 'after:voting_opens_at'],
        ]));

        return back()->with('status', 'Match programmé.');
    }

    public function openVoting(Organizer $organizer, Competition $competition, BattleMatch $match): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $playable = in_array($match->status, [MatchStatus::Scheduled, MatchStatus::Submissions], true)
            && $match->slots()->whereNotNull('participant_id')->count() === 2;

        if (! $playable) {
            throw CompetitionFlowException::matchNotPlayable();
        }

        $match->forceFill([
            'status' => MatchStatus::Voting,
            'voting_opens_at' => $match->voting_opens_at ?? now(),
        ])->save();

        return back()->with('status', 'Vote ouvert.');
    }

    public function close(Request $request, Organizer $organizer, Competition $competition, BattleMatch $match, MatchCloser $closer): RedirectResponse
    {
        $this->authorize('runMatches', $competition);

        $validated = $request->validate(['winner_id' => ['nullable', 'integer']]);

        $closer->close($match, isset($validated['winner_id']) ? (int) $validated['winner_id'] : null);

        return back()->with('status', 'Match clôturé.');
    }
}
