<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Moderation state of a performance.
 */
enum PerformanceStatus: string
{
    use EnumHelpers;

    case Pending = 'en_attente';
    case Approved = 'validee';
    case Rejected = 'rejetee';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Approved => 'Validée',
            self::Rejected => 'Rejetée',
        };
    }
}
