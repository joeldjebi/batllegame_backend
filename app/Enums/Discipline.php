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

    /**
     * Heroicon name (outline set) used in the back-office.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Rap => 'microphone',
            self::Singing => 'musical-note',
            self::Freestyle => 'bolt',
            self::Slam => 'chat-bubble-bottom-center-text',
            self::Beatbox => 'speaker-wave',
            self::Other => 'sparkles',
        };
    }
}
