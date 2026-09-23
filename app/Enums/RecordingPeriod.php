<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * When the media was recorded compared with the submission period.
 */
enum RecordingPeriod: string implements HasBadge
{
    use EnumHelpers;

    case During = 'pendant';
    case Before = 'avant';
    case Inconsistent = 'incoherente';
    case Unknown = 'inconnue';

    public function label(): string
    {
        return match ($this) {
            self::During => 'Enregistré pendant la période',
            self::Before => 'Enregistré avant la période',
            self::Inconsistent => 'Date incohérente',
            self::Unknown => 'Date d\'enregistrement inconnue',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::During => 'green',
            self::Before => 'red',
            self::Inconsistent => 'amber',
            self::Unknown => 'gray',
        };
    }
}
