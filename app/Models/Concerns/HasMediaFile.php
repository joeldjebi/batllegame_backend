<?php

namespace App\Models\Concerns;

use App\Enums\MediaOrigin;
use App\Enums\PerformanceStatus;
use App\Enums\RecordingPeriod;
use App\Support\MediaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Models storing one media file (media_path on media_disk) and its provenance
 * (recorded_at, media_origin, media_metadata, client_modified_at), plus its poster and
 * display size once optimized for streaming (poster_path, width, height, optimized_at).
 */
trait HasMediaFile
{
    /**
     * published_at follows the status: set when the media becomes public, cleared otherwise.
     */
    public static function bootHasMediaFile(): void
    {
        static::saving(function (self $media): void {
            if (! $media->isDirty('status')) {
                return;
            }

            $public = $media->status === PerformanceStatus::Approved;
            $media->published_at = $public ? ($media->published_at ?? now()) : null;
        });
    }

    /** Minutes of tolerance around the period (clocks, time zones). */
    private int $recordingTolerance = 10;

    public function initializeHasMediaFile(): void
    {
        $this->mergeCasts([
            'recorded_at' => 'datetime',
            'media_origin' => MediaOrigin::class,
            'media_metadata' => 'array',
            'client_modified_at' => 'datetime',
            'width' => 'integer',
            'height' => 'integer',
            'optimized_at' => 'datetime',
            'published_at' => 'datetime',
        ]);
    }

    public function mediaUrl(): ?string
    {
        return MediaUrl::for($this->media_disk, $this->media_path);
    }

    /**
     * Still image shown before the video plays (feeds, players), null until optimized.
     */
    public function posterUrl(): ?string
    {
        return MediaUrl::for($this->media_disk, $this->poster_path);
    }

    /**
     * Removes the file and its poster; the row is then ready for a new upload (not saved).
     */
    public function deleteMedia(): void
    {
        $disk = Storage::disk($this->media_disk ?? config('media.disk'));

        foreach (array_filter([$this->media_path, $this->poster_path]) as $path) {
            $disk->delete($path);
            MediaUrl::forget($this->media_disk, $path);
        }

        $this->forceFill(['poster_path' => null, 'width' => null, 'height' => null, 'optimized_at' => null]);
    }

    /**
     * Date of the current file's upload (the row is reused when the media is replaced).
     */
    public function uploadedAt(): Carbon
    {
        $stored = $this->media_metadata['uploaded_at'] ?? null;

        return ($stored ? rescue(fn () => Carbon::parse($stored), null, false) : null) ?? $this->created_at ?? now();
    }

    /**
     * Recording date compared with the submission period (see submissionWindow()).
     */
    public function recordingPeriod(): RecordingPeriod
    {
        if ($this->recorded_at === null) {
            return RecordingPeriod::Unknown;
        }

        [$start] = $this->submissionWindow();
        $uploadedAt = $this->uploadedAt();

        return match (true) {
            // Recorded after it was sent: the device clock or the metadata is wrong.
            $this->recorded_at->greaterThan($uploadedAt->copy()->addMinutes($this->recordingTolerance)) => RecordingPeriod::Inconsistent,
            $start !== null && $this->recorded_at->lessThan($start->copy()->subMinutes($this->recordingTolerance)) => RecordingPeriod::Before,
            default => RecordingPeriod::During,
        };
    }
}
