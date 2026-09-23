<?php

namespace App\Jobs;

use App\Enums\PerformanceStatus;
use App\Models\Contracts\ReviewableMedia;
use App\Services\Media\MediaInspector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Checks an uploaded media (stage submission or pre-selection entry) against
 * its rules (real duration via ffprobe), then publishes it or leaves it for
 * the organizer's review.
 */
class ProcessSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * A few seconds of tolerance for encoders rounding the duration.
     */
    private const int DURATION_TOLERANCE = 2;

    /**
     * @param  Model&ReviewableMedia  $media
     */
    public function __construct(public Model $media) {}

    public function handle(MediaInspector $inspector): void
    {
        /** @var (Model&ReviewableMedia)|null $media */
        $media = $this->media->fresh();

        if ($media === null || $media->status !== PerformanceStatus::Processing) {
            return;
        }

        $maxDuration = $media->maxMediaDuration();
        $duration = $this->withLocalCopy($media, fn (string $path) => $inspector->duration($path));

        if ($duration !== null && $duration > $maxDuration + self::DURATION_TOLERANCE) {
            $media->forceFill([
                'duration_seconds' => (int) round($duration),
                'status' => PerformanceStatus::Rejected,
                'rejection_reason' => sprintf('Durée de %d s : la limite est de %d s.', round($duration), $maxDuration),
            ])->save();

            return;
        }

        $media->forceFill([
            'duration_seconds' => $duration !== null ? (int) round($duration) : null,
            'status' => $media->requiresReview() ? PerformanceStatus::Pending : PerformanceStatus::Approved,
        ])->save();
    }

    /**
     * ffprobe needs a local file: remote disks (S3) are copied to a temp file.
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    private function withLocalCopy(Model $media, callable $callback): mixed
    {
        $diskName = $media->media_disk ?? config('media.disk');
        $disk = Storage::disk($diskName);

        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            return $callback($disk->path($media->media_path));
        }

        $temp = tempnam(sys_get_temp_dir(), 'media');
        file_put_contents($temp, $disk->readStream($media->media_path));

        try {
            return $callback($temp);
        } finally {
            @unlink($temp);
        }
    }
}
