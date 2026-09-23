<?php

namespace App\Services\Media;

interface MediaInspector
{
    /**
     * Duration in seconds, or null when it cannot be determined.
     */
    public function duration(string $absolutePath): ?float;
}
