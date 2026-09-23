<?php

namespace App\Policies;

use App\Enums\OrganizerPermission;
use App\Models\Organizer;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizerAccess;
use Illuminate\Auth\Access\Response;

class OrganizerPolicy
{
    use ChecksOrganizerAccess;

    public function view(User $user, Organizer $organizer): Response
    {
        return $this->organizerAccess($user, $organizer, OrganizerPermission::ViewCompetitions, writes: false);
    }

    public function update(User $user, Organizer $organizer): Response
    {
        return $this->organizerAccess($user, $organizer, OrganizerPermission::EditProfile);
    }

    public function manageMembers(User $user, Organizer $organizer): Response
    {
        return $this->organizerAccess($user, $organizer, OrganizerPermission::ManageMembers);
    }

    /**
     * Verify or suspend an organizer: platform admins only (granted by before()).
     */
    public function moderate(User $user, Organizer $organizer): bool
    {
        return false;
    }
}
