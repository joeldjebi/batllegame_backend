<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Platform-wide roles managed by spatie/laravel-permission (separate from organizer roles).
 */
enum PlatformRole: string
{
    use EnumHelpers;

    case Admin = 'platform-admin';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Super-admin plateforme',
        };
    }
}
