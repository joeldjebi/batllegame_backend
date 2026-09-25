<?php

namespace App\Services\Media;

use App\Enums\MediaType;

interface MediaOptimizer
{
    /**
     * Prepares a local media file for streaming. Returns null when the file cannot be
     * processed (tool missing, unreadable file): the original is then kept as is.
     * Produced files are temporary: the caller stores them, then deletes them.
     */
    public function optimize(string $absolutePath, MediaType $type): ?OptimizedMedia;
}
