<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Rows created by the local seeders, so the super-admin can remove them in one go.
 * Demo: generated content (competitions, fake artists, judges and fans).
 * TestAccount: the SEED_* accounts and the test organizer (kept unless asked).
 */
enum SeedKind: string
{
    use EnumHelpers;

    case Demo = 'demo';
    case TestAccount = 'compte_test';
}
