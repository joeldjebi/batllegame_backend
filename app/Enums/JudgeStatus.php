<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * State of a judge invitation.
 */
enum JudgeStatus: string implements HasBadge
{
    use EnumHelpers;

    case Invited = 'invite';
    case Accepted = 'accepte';
    case Declined = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invité',
            self::Accepted => 'Accepté',
            self::Declined => 'Refusé',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Invited => 'amber',
            self::Accepted => 'green',
            self::Declined => 'red',
        };
    }
}
