<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Role of a user inside an organizer (never used for platform administration).
 */
enum OrganizerRole: string
{
    use EnumHelpers;

    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propriétaire',
            self::Admin => 'Administrateur',
            self::Staff => 'Staff',
        };
    }
}
