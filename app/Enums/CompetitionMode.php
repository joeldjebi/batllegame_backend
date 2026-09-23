<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * How a competition (or a phase overriding it) takes place.
 */
enum CompetitionMode: string
{
    use EnumHelpers;

    case OnSite = 'presentiel';
    case Online = 'en_ligne';
    case Hybrid = 'mixte';

    public function label(): string
    {
        return match ($this) {
            self::OnSite => 'Présentiel',
            self::Online => 'En ligne',
            self::Hybrid => 'Mixte',
        };
    }
}
