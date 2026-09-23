<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * Role of a user inside an organizer (never used for platform administration).
 */
enum OrganizerRole: string implements HasBadge
{
    use EnumHelpers;

    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    /**
     * Permission matrix: staff runs the event, admin also configures it,
     * owner also manages members and can delete.
     *
     * @return list<OrganizerPermission>
     */
    public function permissions(): array
    {
        $staff = [
            OrganizerPermission::ViewCompetitions,
            OrganizerPermission::ManageRegistrations,
            OrganizerPermission::RunMatches,
        ];

        $admin = [
            ...$staff,
            OrganizerPermission::ManageCompetitions,
            OrganizerPermission::EditProfile,
        ];

        return match ($this) {
            self::Staff => $staff,
            self::Admin => $admin,
            self::Owner => [...$admin, OrganizerPermission::DeleteCompetitions, OrganizerPermission::ManageMembers],
        };
    }

    public function grants(OrganizerPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propriétaire',
            self::Admin => 'Administrateur',
            self::Staff => 'Staff',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Owner => 'violet',
            self::Admin => 'blue',
            self::Staff => 'gray',
        };
    }
}
