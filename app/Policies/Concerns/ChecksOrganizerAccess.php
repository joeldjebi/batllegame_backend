<?php

namespace App\Policies\Concerns;

use App\Enums\OrganizerPermission;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Shared checks for back-office policies.
 *
 * Non-members get a 404 (not a 403) so the existence of another organizer's
 * resources is never revealed.
 */
trait ChecksOrganizerAccess
{
    /**
     * Platform admins bypass organizer checks in the back-office.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isPlatformAdmin() ? true : null;
    }

    protected function organizerAccess(User $user, Organizer $organizer, OrganizerPermission $permission, bool $writes = true): Response
    {
        $role = $user->roleIn($organizer);

        if ($role === null) {
            return Response::denyAsNotFound();
        }

        if (! $role->grants($permission)) {
            return Response::deny('Votre rôle ne permet pas cette action.');
        }

        if ($writes && $organizer->isSuspended()) {
            return Response::deny('Cet organisateur est suspendu : aucune modification possible.');
        }

        return Response::allow();
    }
}
