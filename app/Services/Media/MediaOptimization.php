<?php

namespace App\Services\Media;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores what MediaOptimizer produced next to the original: the optimized video
 * replaces the uploaded file, the poster is saved beside it. Never blocks a
 * submission: any failure is logged and the original file stays in place.
 */
class MediaOptimization
{
    public function __construct(private MediaOptimizer $optimizer) {}

    public static function enabled(): bool
    {
        return (bool) config('media.optimize');
    }

    /**
     * @param  Model  $media  A Performance or a PreselectionSubmission (HasMediaFile).
     * @param  string  $localPath  Local copy of its current file.
     */
    public function apply(Model $media, string $localPath): bool
    {
        if (! self::enabled() || $media->media_path === null || $media->media_type === null) {
            return false;
        }

        $currentPath = $media->media_path;

        try {
            $result = $this->optimizer->optimize($localPath, $media->media_type);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        if ($result === null) {
            return false;
        }

        try {
            // Replaced by the artist meanwhile: this result is about a file that no longer exists.
            if ($media->newQuery()->whereKey($media->getKey())->value('media_path') !== $currentPath) {
                return false;
            }

            $diskName = $media->media_disk ?? config('media.disk');
            $disk = Storage::disk($diskName);
            $directory = trim(dirname($currentPath), '.');
            $base = ($directory !== '' ? $directory.'/' : '').Str::uuid();
            $changes = ['width' => $result->width, 'height' => $result->height, 'optimized_at' => now()];

            if ($result->videoPath !== null) {
                $disk->putFileAs(dirname($base), $result->videoPath, basename($base).'.mp4');
                $changes += ['media_path' => "{$base}.mp4", 'mime_type' => 'video/mp4', 'size_bytes' => filesize($result->videoPath)];
            }

            if ($result->posterPath !== null) {
                $disk->putFileAs(dirname($base), $result->posterPath, basename($base).'-poster.jpg');
                $changes['poster_path'] = "{$base}-poster.jpg";
            }

            $previousPoster = $media->poster_path;
            $media->forceFill($changes)->save();

            foreach (array_filter([isset($changes['media_path']) ? $currentPath : null, isset($changes['poster_path']) ? $previousPoster : null]) as $old) {
                $disk->delete($old);
                MediaUrl::forget($diskName, $old);
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Optimized media could not be stored; the original file is kept.', ['media' => $media::class.'#'.$media->getKey(), 'error' => $e->getMessage()]);

            return false;
        } finally {
            $result->cleanup();
        }
    }

    /**
     * Runs $callback on a local copy of the media file (remote disks are downloaded to a temp file).
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    public static function withLocalCopy(Model $media, callable $callback): mixed
    {
        $diskName = $media->media_disk ?? config('media.disk');
        $disk = Storage::disk($diskName);

        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            return $callback($disk->path($media->media_path));
        }

        $temp = tempnam(sys_get_temp_dir(), 'media');
        $stream = $disk->readStream($media->media_path);
        file_put_contents($temp, $stream);
        is_resource($stream) && fclose($stream);

        try {
            return $callback($temp);
        } finally {
            @unlink($temp);
        }
    }
}
