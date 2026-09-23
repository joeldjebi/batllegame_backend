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

    /**
     * Platform roles are stored on the "web" guard, whatever guard is active
     * (the admin console runs on its own "admin" guard).
     */
    public const string GUARD = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Super-admin plateforme',
        };
    }
}
