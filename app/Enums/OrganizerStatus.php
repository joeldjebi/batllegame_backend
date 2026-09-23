<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Verification state of an organizer on the platform.
 */
enum OrganizerStatus: string
{
    use EnumHelpers;

    case Pending = 'en_attente';
    case Verified = 'verifie';
    case Suspended = 'suspendu';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Verified => 'Vérifié',
            self::Suspended => 'Suspendu',
        };
    }
}
