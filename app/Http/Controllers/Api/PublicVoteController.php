<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\PublicVote;
use App\Services\VotingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicVoteController extends Controller
{
    /**
     * $match is resolved through $competition->matches() (scoped binding).
     */
    public function store(Request $request, Competition $competition, BattleMatch $match, VotingService $voting): JsonResponse
    {
        $this->authorize('create', [PublicVote::class, $match]);

        $request->validate(['participant_id' => ['required', 'integer']]);

        $voting->cast($request->user(), $match, $request->integer('participant_id'), $request->input('vote_code'), $request->header('X-Device-Id'), $request->ip());

        return response()->json(['message' => 'Vote enregistré.'], 201);
    }
}
