<?php

namespace App\Jobs;

use App\Services\Media\MediaOptimization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Streaming optimization of a media already published or reviewed: organizers'
 * captations, and files uploaded before optimization existed (media:optimize).
 */
class OptimizeMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    /**
     * @param  Model  $media  A Performance or a PreselectionSubmission.
     */
    public function __construct(public Model $media) {}

    public function handle(MediaOptimization $optimization): void
    {
        $media = $this->media->fresh();

        if ($media === null || $media->media_path === null || $media->optimized_at !== null || ! MediaOptimization::enabled()) {
            return;
        }

        MediaOptimization::withLocalCopy($media, fn (string $path) => $optimization->apply($media, $path));
    }
}
