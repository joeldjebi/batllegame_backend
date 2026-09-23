<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\JuryScore;
use App\Services\JuryScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JuryScoreController extends Controller
{
    /**
     * Store (or overwrite) the scores of the current judge for one participant.
     * $match is resolved through $competition->matches() (scoped binding).
     */
    public function store(Request $request, Competition $competition, BattleMatch $match, JuryScoringService $scoring): JsonResponse
    {
        $this->authorize('create', [JuryScore::class, $match]);

        $judge = $competition->judges()->where('user_id', $request->user()->id)->firstOrFail();
        $scoring->store($judge, $match, $request->all());

        return response()->json(['message' => 'Notes enregistrées.'], 201);
    }
}
