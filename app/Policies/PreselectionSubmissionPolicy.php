<?php

namespace App\Policies;

use App\Enums\JudgeStatus;
use App\Enums\PreselectionState;
use App\Models\PreselectionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Public likes and jury scores on pre-selection entries.
 */
class PreselectionSubmissionPolicy
{
    public function like(User $user, PreselectionSubmission $entry): Response
    {
        if (! $user->hasVerifiedPhone()) {
            return Response::deny('Vous devez vérifier votre numéro de téléphone pour liker.');
        }

        if ($entry->competition->organizer->isSuspended() || ! $entry->preselection->isOpen()) {
            return Response::deny("La présélection n'est pas ouverte.");
        }

        if (! $entry->isPublished()) {
            return Response::denyAsNotFound();
        }

        if ($entry->participant->user_id === $user->id) {
            return Response::deny('Vous ne pouvez pas liker votre propre prestation.');
        }

        if ($user->isJudgeOf($entry->competition_id)) {
            return Response::deny('Les membres du jury ne likent pas les prestations.');
        }

        return Response::allow();
    }

    public function score(User $user, PreselectionSubmission $entry): Response
    {
        $isJudge = $entry->competition->judges()
            ->where('user_id', $user->id)
            ->where('status', JudgeStatus::Accepted)
            ->exists();

        if (! $isJudge) {
            return Response::denyAsNotFound();
        }

        $state = $entry->preselection->state();

        if ($entry->competition->organizer->isSuspended() || ! in_array($state, [PreselectionState::Open, PreselectionState::Closed], true)) {
            return Response::deny("La notation de la présélection n'est pas ouverte.");
        }

        return $entry->isPublished() ? Response::allow() : Response::denyAsNotFound();
    }
}
