<?php

namespace App\Policies;

use App\Enums\CompetitionStatus;
use App\Enums\OrganizerPermission;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizerAccess;
use Illuminate\Auth\Access\Response;

/**
 * Back-office access to a competition and everything below it (phases, groups,
 * participants, matches, judges, criteria). Child resources are authorized
 * against their competition with the relevant ability.
 */
class CompetitionPolicy
{
    use ChecksOrganizerAccess;

    public function viewAny(User $user, Organizer $organizer): Response
    {
        return $this->organizerAccess($user, $organizer, OrganizerPermission::ViewCompetitions, writes: false);
    }

    public function view(User $user, Competition $competition): Response
    {
        return $this->organizerAccess($user, $competition->organizer, OrganizerPermission::ViewCompetitions, writes: false);
    }

    public function create(User $user, Organizer $organizer): Response
    {
        return $this->organizerAccess($user, $organizer, OrganizerPermission::ManageCompetitions);
    }

    /**
     * Edit the competition and its structure (phases, judges, criteria).
     */
    public function update(User $user, Competition $competition): Response
    {
        $access = $this->organizerAccess($user, $competition->organizer, OrganizerPermission::ManageCompetitions);

        if ($access->allowed() && $this->isOver($competition)) {
            return Response::deny('Cette compétition est terminée ou annulée.');
        }

        return $access;
    }

    /**
     * Change the lifecycle status. Opening registrations requires a verified organizer.
     */
    public function changeStatus(User $user, Competition $competition, CompetitionStatus $status): Response
    {
        $access = $this->organizerAccess($user, $competition->organizer, OrganizerPermission::ManageCompetitions);

        if ($access->denied()) {
            return $access;
        }

        if (! $competition->status->canTransitionTo($status)) {
            return Response::deny("Transition impossible de « {$competition->status->label()} » vers « {$status->label()} ».");
        }

        if ($status === CompetitionStatus::Registration && ! $competition->organizer->isVerified()) {
            return Response::deny("L'organisateur doit être vérifié pour ouvrir les inscriptions.");
        }

        return Response::allow();
    }

    public function delete(User $user, Competition $competition): Response
    {
        $access = $this->organizerAccess($user, $competition->organizer, OrganizerPermission::DeleteCompetitions);

        if ($access->allowed() && ! in_array($competition->status, [CompetitionStatus::Draft, CompetitionStatus::Cancelled], true)) {
            return Response::deny('Seule une compétition en brouillon ou annulée peut être supprimée.');
        }

        return $access;
    }

    public function manageRegistrations(User $user, Competition $competition): Response
    {
        return $this->organizerAccess($user, $competition->organizer, OrganizerPermission::ManageRegistrations);
    }

    public function runMatches(User $user, Competition $competition): Response
    {
        $access = $this->organizerAccess($user, $competition->organizer, OrganizerPermission::RunMatches);

        if ($access->allowed() && $this->isOver($competition)) {
            return Response::deny('Cette compétition est terminée ou annulée.');
        }

        return $access;
    }

    private function isOver(Competition $competition): bool
    {
        return in_array($competition->status, [CompetitionStatus::Finished, CompetitionStatus::Cancelled], true);
    }
}
