<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Public URL of a stored file: a plain URL on a local disk, a temporary signed URL on an
 * S3 compatible disk (Wasabi bucket kept private).
 *
 * A signed URL is reused for half its lifetime: the same file keeps the same URL across
 * renders (a video does not restart when a live region re-renders, the browser cache
 * works) and it is always valid for at least half the TTL when handed out.
 */
class MediaUrl
{
    public static function for(?string $disk, ?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $disk ??= config('media.disk');

        if (config("filesystems.disks.{$disk}.driver") !== 's3') {
            return Storage::disk($disk)->url($path);
        }

        $minutes = max(2, (int) config('media.signed_url_minutes'));
        $key = "media-url:{$disk}:{$path}";

        return Cache::remember($key, now()->addMinutes(intdiv($minutes, 2)),
            fn () => Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($minutes)));
    }

    /**
     * Drop the cached URL of a deleted or replaced file.
     */
    public static function forget(?string $disk, ?string $path): void
    {
        Cache::forget('media-url:'.($disk ?? config('media.disk')).':'.$path);
    }
}
