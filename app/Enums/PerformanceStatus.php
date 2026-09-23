<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * Moderation state of a performance.
 */
enum PerformanceStatus: string implements HasBadge
{
    use EnumHelpers;

    case Processing = 'traitement';
    case Pending = 'en_attente';
    case Approved = 'validee';
    case Rejected = 'rejetee';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'En traitement',
            self::Pending => 'À valider',
            self::Approved => 'Validée',
            self::Rejected => 'Rejetée',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Processing => 'blue',
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }
}
