<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Artistic discipline of a competition.
 */
enum Discipline: string
{
    use EnumHelpers;

    case Rap = 'rap';
    case Singing = 'chant';
    case Freestyle = 'freestyle';
    case Slam = 'slam';
    case Beatbox = 'beatbox';
    case Other = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Rap => 'Rap',
            self::Singing => 'Chant',
            self::Freestyle => 'Freestyle',
            self::Slam => 'Slam',
            self::Beatbox => 'Beatbox',
            self::Other => 'Autre',
        };
    }
}
