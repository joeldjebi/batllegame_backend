<?php

namespace App\Services;

use App\Models\BattleMatch;
use App\Models\Phase;
use App\Models\PublicVote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Public votes, shared by the mobile API and the web voting area.
 * Authorization (verified phone, open vote, conflicts) is the PublicVotePolicy's job.
 */
class VotingService
{
    public function cast(User $voter, BattleMatch $match, int $participantId, ?string $voteCode = null, ?string $deviceId = null, ?string $ip = null): PublicVote
    {
        $participants = $match->activeSlots()->pluck('participant_id')->all();

        if (! in_array($participantId, $participants, true)) {
            throw ValidationException::withMessages(['participant_id' => $match->isGroupMatch() ? 'Cet artiste ne fait pas partie de cette poule.' : 'Ce participant ne joue pas ce match.']);
        }

        // On-site with room code: only people present in the room can vote.
        if ($match->vote_code !== null && ! hash_equals($match->vote_code, (string) $voteCode)) {
            throw ValidationException::withMessages(['vote_code' => 'Code de salle invalide.']);
        }

        if ($match->publicVotes()->where('user_id', $voter->id)->exists()) {
            abort(409, 'Vous avez déjà voté pour ce match.');
        }

        $maxPerDevice = $match->competition->settings->maxVotesPerDevice;

        if ($deviceId !== null && $maxPerDevice !== null
            && $match->publicVotes()->where('device_id', $deviceId)->count() >= $maxPerDevice) {
            abort(429, 'Trop de votes depuis cet appareil pour ce match.');
        }

        $vote = new PublicVote(['match_id' => $match->id, 'participant_id' => $participantId]);
        $vote->forceFill(['user_id' => $voter->id, 'device_id' => $deviceId, 'ip' => $ip]);

        try {
            // Savepoint: a unique violation (concurrent double vote) must not abort an outer transaction.
            DB::transaction(function () use ($vote, $match, $voter): void {
                // Groups: one vote for the whole phase, whatever the group (the phase row serializes concurrent votes).
                if ($match->isGroupMatch()) {
                    Phase::query()->lockForUpdate()->find($match->phase_id);

                    if (PublicVote::query()->where('user_id', $voter->id)->whereIn('match_id', $match->phase->matches()->select('id'))->exists()) {
                        abort(409, 'Vous avez déjà voté pour cette phase : un seul vote par phase.');
                    }
                }

                $vote->save();
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Vous avez déjà voté pour ce match.');
        }

        return $vote;
    }
}
