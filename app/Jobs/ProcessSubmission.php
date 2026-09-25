<?php

namespace App\Jobs;

use App\Enums\PerformanceStatus;
use App\Models\Contracts\ReviewableMedia;
use App\Services\Media\MediaInspector;
use App\Services\Media\MediaOptimization;
use App\Services\Media\MediaProvenance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks an uploaded media (stage submission or pre-selection entry) against
 * its rules (real duration via ffprobe), reads its provenance (hidden metadata),
 * optimizes it for streaming (lossless when possible, poster image), then
 * publishes it or leaves it for the organizer's review.
 */
class ProcessSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Re-encoding a long HD video can take minutes (keep below the queue retry_after). */
    public int $timeout = 600;

    /**
     * A few seconds of tolerance for encoders rounding the duration.
     */
    private const int DURATION_TOLERANCE = 2;

    /**
     * @param  Model&ReviewableMedia  $media
     */
    public function __construct(public Model $media) {}

    public function handle(MediaInspector $inspector, MediaProvenance $provenance, MediaOptimization $optimization): void
    {
        /** @var (Model&ReviewableMedia)|null $media */
        $media = $this->media->fresh();

        if ($media === null || $media->status !== PerformanceStatus::Processing) {
            return;
        }

        MediaOptimization::withLocalCopy($media, function (string $path) use ($media, $inspector, $provenance, $optimization): void {
            $duration = $inspector->duration($path);
            // Hidden metadata: an indication for the organizer, never blocking.
            $origin = rescue(fn () => $provenance->analyze($path), null);

            if ($origin !== null) {
                $media->forceFill([
                    'recorded_at' => $origin['recorded_at'],
                    'media_origin' => $origin['origin'],
                    'media_metadata' => [...$origin['metadata'], 'uploaded_at' => $media->media_metadata['uploaded_at'] ?? now()->toIso8601String()],
                ]);
            }

            $maxDuration = $media->maxMediaDuration();

            if ($duration !== null && $duration > $maxDuration + self::DURATION_TOLERANCE) {
                $media->forceFill([
                    'duration_seconds' => (int) round($duration),
                    'status' => PerformanceStatus::Rejected,
                    'rejection_reason' => sprintf('Durée de %d s : la limite est de %d s.', round($duration), $maxDuration),
                ])->save();

                return;
            }

            $media->forceFill(['duration_seconds' => $duration !== null ? (int) round($duration) : null])->save();

            // Still « en traitement » meanwhile: the organizer reviews the final file.
            $optimization->apply($media, $path);

            $media->forceFill([
                'status' => $media->requiresReview() ? PerformanceStatus::Pending : PerformanceStatus::Approved,
            ])->save();
        });
    }
}
