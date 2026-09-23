<?php

namespace App\Policies;

use App\Enums\MatchStatus;
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

        if (! $match->acceptsJuryScores()) {
            return Response::deny($match->status === MatchStatus::Voting && $match->juryDeadline()?->isPast()
                ? 'La délibération du jury est close pour ce match.'
                : "La notation n'est pas ouverte pour ce match.");
        }

        return Response::allow();
    }
}
