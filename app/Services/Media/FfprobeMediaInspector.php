<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Reads the duration of a media file with ffprobe (FFmpeg).
 */
class FfprobeMediaInspector implements MediaInspector
{
    public function duration(string $absolutePath): ?float
    {
        $result = Process::timeout(30)->run([
            config('media.ffprobe'), '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $absolutePath,
        ]);

        if (! $result->successful() || ! is_numeric(trim($result->output()))) {
            Log::warning('ffprobe could not read the media duration; the duration check is skipped.', [
                'path' => $absolutePath,
                'error' => trim($result->errorOutput()) ?: 'ffprobe unavailable',
            ]);

            return null;
        }

        return (float) trim($result->output());
    }
}
