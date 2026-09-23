<?php

namespace App\Policies;

use App\Models\Competition;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Mobile app: an artist registering to a competition.
 */
class ParticipantPolicy
{
    public function register(User $user, Competition $competition): Response
    {
        if (! $competition->isRegistrationOpen() || $competition->organizer->isSuspended()) {
            return Response::deny('Les inscriptions ne sont pas ouvertes pour cette compétition.');
        }

        if ($user->isJudgeOf($competition, acceptedOnly: false)) {
            return Response::deny('Un membre du jury ne peut pas participer à la même compétition.');
        }

        if ($user->isParticipantOf($competition)) {
            return Response::deny('Vous êtes déjà inscrit à cette compétition.');
        }

        if ($competition->max_participants !== null
            && $competition->participants()->count() >= $competition->max_participants) {
            return Response::deny('Le nombre maximum de participants est atteint.');
        }

        return Response::allow();
    }
}
