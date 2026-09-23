<?php

namespace App\Enums\Contracts;

/**
 * Enums rendered as a badge in the back-office (<x-ui.badge :value="$enum" />).
 */
interface HasBadge
{
    public function label(): string;

    /**
     * One of: gray, blue, green, amber, red, violet, fuchsia.
     */
    public function tone(): string;
}
