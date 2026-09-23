<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Subscription plan of an organizer.
 */
enum OrganizerPlan: string
{
    use EnumHelpers;

    case Free = 'free';
    case Pro = 'pro';
    case Premium = 'premium';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Gratuit',
            self::Pro => 'Pro',
            self::Premium => 'Premium',
        };
    }
}
