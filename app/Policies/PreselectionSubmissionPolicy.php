<?php

namespace App\Policies;

use App\Enums\JudgeStatus;
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

        if ($entry->competition->organizer->isSuspended() || ! $entry->preselection->publicVotingEnabled()) {
            return Response::deny("Le vote du public n'est pas activé pour cette compétition.");
        }

        if (! $entry->preselection->acceptsLikes()) {
            return Response::deny('Le vote du public est clos.');
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

        if ($entry->competition->organizer->isSuspended() || ! $entry->preselection->acceptsScores()) {
            return Response::deny('La délibération du jury est close.');
        }

        return $entry->isPublished() ? Response::allow() : Response::denyAsNotFound();
    }
}
