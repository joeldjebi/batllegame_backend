<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Origin of a performance media.
 */
enum PerformanceSource: string
{
    use EnumHelpers;

    case Submission = 'soumission';
    case Capture = 'captation';

    public function label(): string
    {
        return match ($this) {
            self::Submission => 'Soumission',
            self::Capture => 'Captation',
        };
    }
}
