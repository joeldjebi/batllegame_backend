<?php

namespace App\Services\Media;

/**
 * Container and stream tags of any media format (complements Mp4MetadataReader).
 */
interface MediaTagReader
{
    /**
     * @return array<string, string> lower-cased tag => value
     */
    public function tags(string $absolutePath): array;
}
