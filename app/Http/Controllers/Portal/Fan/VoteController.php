<?php

namespace App\Http\Controllers\Portal\Fan;

use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\PublicVote;
use App\Services\VotingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class VoteController extends Controller
{
    /**
     * $match is resolved through $competition->matches() (scoped binding).
     */
    public function store(Request $request, Competition $competition, BattleMatch $match, VotingService $voting): RedirectResponse
    {
        $this->authorize('create', [PublicVote::class, $match]);

        $request->validate(['participant_id' => ['required', 'integer']]);

        try {
            $voting->cast($request->user(), $match, $request->integer('participant_id'), $request->input('vote_code'), 'web-'.substr(hash('sha256', (string) $request->userAgent()), 0, 32), $request->ip());
        } catch (HttpExceptionInterface $e) {
            return back()->withErrors(['vote' => $e->getMessage()]);
        }

        return back()->with('status', 'Merci, votre vote a été enregistré !');
    }
}
