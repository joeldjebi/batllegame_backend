<?php

namespace App\Policies;

use App\Models\BattleMatch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Mobile app: a judge scoring a match.
 */
class JuryScorePolicy
{
    public function create(User $user, BattleMatch $match): Response
    {
        if (! $user->isJudgeOf($match->competition_id)) {
            return Response::denyAsNotFound();
        }

        if ($match->competition->organizer->isSuspended() || ! $match->phase->rules->usesJury()) {
            return Response::deny("La notation du jury n'est pas ouverte pour ce match.");
        }

        if (! $match->isVotingOpen()) {
            return Response::deny("La notation n'est pas ouverte pour ce match.");
        }

        return Response::allow();
    }
}
