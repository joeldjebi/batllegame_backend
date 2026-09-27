<?php

namespace App\Services;

use App\Models\Competition;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Competition covers: center-cropped to 16:9 and resized to 1280×720 (WebP when
 * available, else JPEG), on the media disk. The previous cover is deleted.
 */
class CompetitionCoverService
{
    public const int WIDTH = 1280;

    public const int HEIGHT = 720;

    public function store(Competition $competition, UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['cover' => 'Image illisible : envoyez une image JPG, PNG ou WebP.']);
        }

        // Largest 16:9 area in the middle of the image.
        $width = imagesx($source);
        $height = imagesy($source);
        $cropWidth = min($width, (int) round($height * self::WIDTH / self::HEIGHT));
        $cropHeight = (int) round($cropWidth * self::HEIGHT / self::WIDTH);
        $cover = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($cover, $source, 0, 0, intdiv($width - $cropWidth, 2), intdiv($height - $cropHeight, 2), self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);

        $webp = function_exists('imagewebp');
        ob_start();
        $webp ? imagewebp($cover, null, 82) : imagejpeg($cover, null, 85);
        $binary = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($cover);

        $disk = config('media.disk');
        $path = 'covers/'.$competition->id.'-'.Str::random(8).($webp ? '.webp' : '.jpg');
        Storage::disk($disk)->put($path, $binary, ['ContentType' => $webp ? 'image/webp' : 'image/jpeg']);

        $this->delete($competition);
        $competition->forceFill(['cover_path' => $path, 'cover_disk' => $disk])->save();

        return $path;
    }

    public function delete(Competition $competition): void
    {
        if ($competition->cover_path) {
            rescue(fn () => Storage::disk($competition->cover_disk ?? config('media.disk'))->delete($competition->cover_path), report: false);
            MediaUrl::forget($competition->cover_disk, $competition->cover_path);
            $competition->forceFill(['cover_path' => null, 'cover_disk' => null])->save();
        }
    }
}
