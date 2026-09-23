<?php

namespace App\Policies;

use App\Models\BattleMatch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Mobile app: a member of the public voting on a match.
 */
class PublicVotePolicy
{
    public function create(User $user, BattleMatch $match): Response
    {
        if (! $user->hasVerifiedPhone()) {
            return Response::deny('Vous devez vérifier votre numéro de téléphone pour voter.');
        }

        $competition = $match->competition;

        if ($competition->organizer->isSuspended()
            || ! $competition->settings->publicVotingEnabled
            || ! $match->phase->rules->usesPublic()) {
            return Response::deny("Le vote du public n'est pas ouvert pour ce match.");
        }

        if (! $match->isVotingOpen()) {
            return Response::deny("Le vote n'est pas ouvert pour ce match.");
        }

        if ($match->participants()->where('participants.user_id', $user->getKey())->exists()) {
            return Response::deny('Vous ne pouvez pas voter pour un match auquel vous participez.');
        }

        if ($user->isJudgeOf($competition)) {
            return Response::deny('Les membres du jury ne votent pas avec le public.');
        }

        return Response::allow();
    }
}
