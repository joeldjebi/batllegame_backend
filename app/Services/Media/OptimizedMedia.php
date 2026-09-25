<?php

namespace App\Services\Media;

/**
 * Result of MediaOptimizer: a new video file (null = keep the original), a poster
 * image, and the display size of the video (rotation applied).
 */
final readonly class OptimizedMedia
{
    public function __construct(
        public ?string $videoPath = null,
        public ?string $posterPath = null,
        public ?int $width = null,
        public ?int $height = null,
        public bool $reencoded = false,
    ) {}

    public function cleanup(): void
    {
        foreach (array_filter([$this->videoPath, $this->posterPath]) as $path) {
            @unlink($path);
        }
    }
}
