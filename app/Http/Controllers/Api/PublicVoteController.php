<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BattleMatch;
use App\Models\Competition;
use App\Models\PublicVote;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicVoteController extends Controller
{
    /**
     * $match is resolved through $competition->matches() (scoped binding).
     */
    public function store(Request $request, Competition $competition, BattleMatch $match): JsonResponse
    {
        $this->authorize('create', [PublicVote::class, $match]);

        $validated = $request->validate([
            // Only one of the two participants of this match.
            'participant_id' => ['required', 'integer', Rule::in($match->slots()->whereNotNull('participant_id')->pluck('participant_id'))],
        ]);

        // On-site with room code: only people present in the room can vote.
        if ($match->vote_code !== null && ! hash_equals($match->vote_code, (string) $request->input('vote_code'))) {
            throw ValidationException::withMessages(['vote_code' => 'Code de salle invalide.']);
        }

        if ($match->publicVotes()->where('user_id', $request->user()->id)->exists()) {
            abort(409, 'Vous avez déjà voté pour ce match.');
        }

        $deviceId = $request->header('X-Device-Id');
        $maxPerDevice = $competition->settings->maxVotesPerDevice;

        if ($deviceId !== null && $maxPerDevice !== null
            && $match->publicVotes()->where('device_id', $deviceId)->count() >= $maxPerDevice) {
            abort(429, 'Trop de votes depuis cet appareil pour ce match.');
        }

        $vote = new PublicVote(['match_id' => $match->id, 'participant_id' => $validated['participant_id']]);
        $vote->forceFill([
            'user_id' => $request->user()->id,
            'device_id' => $deviceId,
            'ip' => $request->ip(),
        ]);

        try {
            // Savepoint: a unique violation (concurrent double vote) must not abort an outer transaction.
            DB::transaction(fn () => $vote->save());
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Vous avez déjà voté pour ce match.');
        }

        return response()->json(['message' => 'Vote enregistré.'], 201);
    }
}
