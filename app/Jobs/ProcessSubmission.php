<?php

namespace App\Jobs;

use App\Enums\PerformanceStatus;
use App\Models\Performance;
use App\Services\Media\MediaInspector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Checks an uploaded submission against the phase rules (real duration via
 * ffprobe), then publishes it or leaves it for the organizer's review.
 */
class ProcessSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * A few seconds of tolerance for encoders rounding the duration.
     */
    private const int DURATION_TOLERANCE = 2;

    public function __construct(public Performance $performance) {}

    public function handle(MediaInspector $inspector): void
    {
        $performance = $this->performance->fresh(['stage.phase.competition']);

        if ($performance === null || $performance->status !== PerformanceStatus::Processing) {
            return;
        }

        $rules = $performance->stage->phase->rules;
        $duration = $this->withLocalCopy($performance, fn (string $path) => $inspector->duration($path));

        if ($duration !== null && $duration > $rules->mediaMaxDuration + self::DURATION_TOLERANCE) {
            $performance->forceFill([
                'duration_seconds' => (int) round($duration),
                'status' => PerformanceStatus::Rejected,
                'rejection_reason' => sprintf('Durée de %d s : la limite est de %d s.', round($duration), $rules->mediaMaxDuration),
            ])->save();

            return;
        }

        $performance->forceFill([
            'duration_seconds' => $duration !== null ? (int) round($duration) : null,
            'status' => $performance->stage->phase->competition->settings->submissionsRequireApproval
                ? PerformanceStatus::Pending
                : PerformanceStatus::Approved,
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
    private function withLocalCopy(Performance $performance, callable $callback): mixed
    {
        $disk = Storage::disk($performance->media_disk ?? config('media.disk'));

        if (method_exists($disk, 'path') && config("filesystems.disks.{$performance->media_disk}.driver") === 'local') {
            return $callback($disk->path($performance->media_path));
        }

        $temp = tempnam(sys_get_temp_dir(), 'media');
        file_put_contents($temp, $disk->readStream($performance->media_path));

        try {
            return $callback($temp);
        } finally {
            @unlink($temp);
        }
    }
}
