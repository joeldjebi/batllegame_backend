<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Models storing one media file (media_path on media_disk).
 */
trait HasMediaFile
{
    public function mediaUrl(): ?string
    {
        return $this->media_path ? Storage::disk($this->media_disk ?? config('media.disk'))->url($this->media_path) : null;
    }

    public function deleteMedia(): void
    {
        if ($this->media_path) {
            Storage::disk($this->media_disk ?? config('media.disk'))->delete($this->media_path);
        }
    }
}
