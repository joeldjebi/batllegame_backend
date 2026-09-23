<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * How participants are distributed into groups.
 */
enum GroupDrawMethod: string
{
    use EnumHelpers;

    case Random = 'tirage';
    case Seeded = 'seed';

    public function label(): string
    {
        return match ($this) {
            self::Random => 'Tirage au sort',
            self::Seeded => 'Par tête de série',
        };
    }
}
