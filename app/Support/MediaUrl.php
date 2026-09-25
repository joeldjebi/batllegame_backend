<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Public URL of a stored file: a plain URL on a local disk, a temporary signed URL on an
 * S3 compatible disk (Wasabi bucket kept private). Signing is local, no network call.
 */
class MediaUrl
{
    public static function for(?string $disk, ?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $disk ??= config('media.disk');
        $storage = Storage::disk($disk);

        return config("filesystems.disks.{$disk}.driver") === 's3'
            ? $storage->temporaryUrl($path, now()->addMinutes(config('media.signed_url_minutes')))
            : $storage->url($path);
    }
}
