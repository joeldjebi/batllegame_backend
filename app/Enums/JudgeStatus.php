<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * State of a judge invitation.
 */
enum JudgeStatus: string
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
}
