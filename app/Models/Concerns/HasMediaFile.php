<?php

namespace App\Models\Concerns;

use App\Enums\MediaOrigin;
use App\Enums\RecordingPeriod;
use App\Support\MediaUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Models storing one media file (media_path on media_disk) and its provenance
 * (recorded_at, media_origin, media_metadata, client_modified_at).
 */
trait HasMediaFile
{
    /** Minutes of tolerance around the period (clocks, time zones). */
    private int $recordingTolerance = 10;

    public function initializeHasMediaFile(): void
    {
        $this->mergeCasts([
            'recorded_at' => 'datetime',
            'media_origin' => MediaOrigin::class,
            'media_metadata' => 'array',
            'client_modified_at' => 'datetime',
        ]);
    }

    public function mediaUrl(): ?string
    {
        return MediaUrl::for($this->media_disk, $this->media_path);
    }

    public function deleteMedia(): void
    {
        if ($this->media_path) {
            Storage::disk($this->media_disk ?? config('media.disk'))->delete($this->media_path);
        }
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
